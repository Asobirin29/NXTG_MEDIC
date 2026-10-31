<?php

namespace App\Modules\Import\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Import\Importers\DrugImporter;
use App\Modules\Import\Importers\Importer;
use App\Modules\Import\Importers\PatientImporter;
use App\Modules\Import\Importers\ServiceImporter;
use App\Modules\Import\Importers\StockImporter;
use App\Modules\Import\Jobs\ProcessImport;
use App\Modules\Import\Models\Import;
use App\Modules\Import\Support\Spreadsheet;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Impor dua tahap (PRD §6.6): unggah → periksa semua baris (pratinjau +
 * kesalahan per baris, belum ada data yang disimpan) → konfirmasi → simpan
 * baris yang valid. File kecil diproses langsung, file besar lewat antrean.
 */
class ImportManager
{
    public const TYPES = [
        'drugs' => DrugImporter::class,
        'stock' => StockImporter::class,
        'services' => ServiceImporter::class,
        'patients' => PatientImporter::class,
    ];

    /** Batas baris yang diproses langsung tanpa menunggu worker antrean. */
    public const SYNC_LIMIT = 300;

    public function __construct(private AuditLogger $audit) {}

    public function importer(string $type): Importer
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return app(self::TYPES[$type]);
    }

    /** @return array<string, Importer> jenis yang boleh dipakai user ini */
    public function available(User $user): array
    {
        return collect(array_keys(self::TYPES))
            ->mapWithKeys(fn ($t) => [$t => $this->importer($t)])
            ->filter(fn (Importer $i) => $user->can($i->ability()))
            ->all();
    }

    public function upload(string $type, UploadedFile $file, User $user): Import
    {
        $importer = $this->importer($type);
        $extension = strtolower($file->getClientOriginalExtension());

        $import = Import::create([
            'user_id' => $user->id,
            'type' => $type,
            'status' => 'previewed',
            'original_name' => $file->getClientOriginalName(),
            'path' => $file->storeAs('imports/'.$user->tenant_id, uniqid('imp_').'.'.$extension, 'local'),
        ]);

        try {
            $result = $this->check($import, $importer);
        } catch (ValidationException $e) {
            Storage::disk('local')->delete($import->path);
            $import->delete();

            throw $e;
        }

        $import->forceFill([
            'total_rows' => $result['total'],
            'valid_rows' => count($result['valid']),
            'error_rows' => count($result['errors']),
            'errors' => array_slice($result['errors'], 0, 500, true),
            'preview' => array_map([$importer, 'previewRow'], array_slice($result['valid'], 0, 15, true)),
        ])->save();

        return $import;
    }

    public function confirm(Import $import): void
    {
        if ($import->status !== 'previewed') {
            throw ValidationException::withMessages(['import' => 'Impor ini sudah diproses.']);
        }
        if ($import->valid_rows === 0) {
            throw ValidationException::withMessages(['import' => 'Tidak ada baris valid untuk diimpor. Perbaiki file lalu unggah ulang.']);
        }

        $import->forceFill(['status' => 'queued'])->save();

        $import->valid_rows <= self::SYNC_LIMIT ? ProcessImport::dispatchSync($import->id) : ProcessImport::dispatch($import->id);
    }

    /** Dipanggil job: baca ulang & periksa ulang (data bisa berubah sejak pratinjau), lalu simpan. */
    public function process(Import $import): void
    {
        $import->forceFill(['status' => 'processing', 'started_at' => now()])->save();
        $importer = $this->importer($import->type);

        try {
            $result = $this->check($import, $importer);
            $count = $result['valid'] ? $importer->import($result['valid'], $import->user, $import->original_name) : 0;

            $import->forceFill([
                'status' => 'completed',
                'imported_rows' => $count,
                'valid_rows' => count($result['valid']),
                'error_rows' => count($result['errors']),
                'errors' => array_slice($result['errors'], 0, 500, true),
                'finished_at' => now(),
            ])->save();

            $this->audit->log('import.completed', $import, null, ['type' => $import->type, 'rows' => $count, 'skipped' => count($result['errors'])], null, $import->user_id);
        } catch (Throwable $e) {
            report($e);
            $import->forceFill(['status' => 'failed', 'failure_message' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => now()])->save();
        } finally {
            // File sumber bisa berisi NIK/data pribadi: tidak disimpan lebih lama dari perlu.
            Storage::disk('local')->delete($import->path);
        }
    }

    /**
     * @return array{total: int, valid: array<int, array>, errors: array<int, list<string>>}
     */
    private function check(Import $import, Importer $importer): array
    {
        $path = Storage::disk('local')->path($import->path);
        $importer->prepare();

        $valid = [];
        $errors = [];
        $total = 0;
        $headersChecked = false;

        try {
            foreach (Spreadsheet::rows($path, $import->extension()) as $line => $row) {
                if (! $headersChecked) {
                    $missing = $importer->missingColumns(array_keys($row));
                    if ($missing) {
                        throw ValidationException::withMessages(['file' => 'Kolom wajib tidak ditemukan: '.implode(', ', $missing).'. Gunakan templat yang disediakan.']);
                    }
                    $headersChecked = true;
                }

                if (++$total > Spreadsheet::MAX_ROWS) {
                    throw ValidationException::withMessages(['file' => 'Maksimal '.Spreadsheet::MAX_ROWS.' baris per file. Pecah file menjadi beberapa bagian.']);
                }

                $rowErrors = $importer->validate($row);
                if ($rowErrors) {
                    $errors[$line] = $rowErrors;
                } else {
                    $valid[$line] = $row;
                }
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['file' => 'File tidak bisa dibaca. Pastikan formatnya .xlsx atau .csv yang valid.']);
        }

        if ($total === 0) {
            throw ValidationException::withMessages(['file' => 'File tidak berisi baris data (baris pertama harus judul kolom).']);
        }

        return ['total' => $total, 'valid' => $valid, 'errors' => $errors];
    }
}
