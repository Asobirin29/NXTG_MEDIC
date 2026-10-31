<?php

namespace App\Console\Commands;

use App\Modules\Emr\Models\Icd10;
use Illuminate\Console\Command;

/**
 * Impor kamus ICD-10 lengkap dari CSV: kode,nama_indonesia,nama_inggris
 * (baris judul opsional). Kode yang sudah ada diperbarui.
 */
class ImportIcd10 extends Command
{
    protected $signature = 'medic:import-icd10 {file : Path file CSV} {--delimiter=, : Pemisah kolom}';

    protected $description = 'Impor/perbarui kamus ICD-10 dari file CSV';

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! is_readable($path)) {
            $this->error("File tidak bisa dibaca: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        $batch = [];
        $count = 0;
        $skipped = 0;
        $line = 0;

        while (($row = fgetcsv($handle, 0, $this->option('delimiter'))) !== false) {
            $line++;
            $code = strtoupper(trim($row[0] ?? ''));

            // Lewati baris judul & baris tanpa kode berformat ICD (huruf + 2 digit).
            if (! preg_match('/^[A-Z]\d{2}(\.\d{1,2})?$/', $code) || blank($row[1] ?? null)) {
                if ($line > 1 || ! preg_match('/kode|code/i', $row[0] ?? '')) {
                    $skipped++;
                }

                continue;
            }

            $batch[] = ['code' => $code, 'name_id' => trim($row[1]), 'name_en' => filled($row[2] ?? null) ? trim($row[2]) : null, 'is_active' => true];

            if (count($batch) === 1000) {
                $count += $this->flush($batch);
                $batch = [];
            }
        }

        fclose($handle);
        $count += $this->flush($batch);

        $this->info("{$count} kode ICD-10 diimpor/diperbarui.".($skipped ? " {$skipped} baris dilewati." : ''));

        return self::SUCCESS;
    }

    private function flush(array $batch): int
    {
        if ($batch) {
            Icd10::upsert($batch, ['code'], ['name_id', 'name_en', 'is_active']);
        }

        return count($batch);
    }
}
