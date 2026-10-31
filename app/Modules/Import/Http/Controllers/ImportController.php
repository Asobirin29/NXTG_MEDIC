<?php

namespace App\Modules\Import\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Import\Models\Import;
use App\Modules\Import\Services\ImportManager;
use App\Modules\Import\Support\Spreadsheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportController extends Controller
{
    public function index(Request $request, ImportManager $manager): View
    {
        $types = $manager->available($request->user());

        return view('imports.index', [
            'types' => $types,
            'selected' => array_key_exists($request->query('jenis'), $types) ? $request->query('jenis') : array_key_first($types),
            'history' => Import::with('user')->whereIn('type', array_keys($types))->latest('id')->limit(20)->get(),
        ]);
    }

    public function store(Request $request, ImportManager $manager): RedirectResponse
    {
        $types = $manager->available($request->user());

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys($types))],
            'file' => ['required', 'file', 'max:10240', 'extensions:xlsx,csv'],
        ], ['file.extensions' => 'File harus berformat .xlsx atau .csv.'], ['file' => 'file', 'type' => 'jenis data']);

        // Pratinjau yang ditinggalkan > 1 hari dibersihkan (file bisa berisi data pribadi).
        Import::where('status', 'previewed')->where('created_at', '<', now()->subDay())->get()
            ->each(fn (Import $old) => $this->discardFile($old));

        $import = $manager->upload($data['type'], $request->file('file'), $request->user());

        return redirect()->route('imports.show', $import);
    }

    public function show(Import $import, ImportManager $manager): View
    {
        $importer = $manager->importer($import->type);
        Gate::authorize($importer->ability());

        return view('imports.show', ['import' => $import, 'importer' => $importer]);
    }

    public function confirm(Import $import, ImportManager $manager): RedirectResponse
    {
        Gate::authorize($manager->importer($import->type)->ability());

        $manager->confirm($import);
        $import->refresh();

        return redirect()->route('imports.show', $import)->with(
            $import->status === 'failed' ? 'error' : 'success',
            match ($import->status) {
                'completed' => "{$import->imported_rows} baris berhasil diimpor.",
                'failed' => 'Impor gagal: '.$import->failure_message,
                default => 'Impor masuk antrean dan akan diproses di latar belakang. Halaman ini memperbarui diri otomatis.',
            },
        );
    }

    public function destroy(Import $import, ImportManager $manager): RedirectResponse
    {
        Gate::authorize($manager->importer($import->type)->ability());
        abort_unless($import->status === 'previewed', 422, 'Impor yang sudah diproses tidak bisa dibatalkan.');

        $this->discardFile($import);

        return redirect()->route('imports.index', ['jenis' => $import->type])->with('success', 'Pratinjau dibatalkan, file dihapus.');
    }

    /** Templat .xlsx: judul kolom + satu baris contoh. */
    public function template(string $type, ImportManager $manager): BinaryFileResponse
    {
        $importer = $manager->importer($type);
        Gate::authorize($importer->ability());

        $columns = $importer->columns();
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        Spreadsheet::writeXlsx($path, array_map(fn ($k, $c) => $c[0] ? "{$k}*" : $k, array_keys($columns), $columns), [array_column($columns, 2)]);

        return response()->download($path, "templat-impor-{$type}.xlsx")->deleteFileAfterSend();
    }

    /** Laporan kesalahan per baris (.xlsx) untuk diperbaiki lalu diunggah ulang. */
    public function errors(Import $import, ImportManager $manager): BinaryFileResponse
    {
        Gate::authorize($manager->importer($import->type)->ability());

        $path = tempnam(sys_get_temp_dir(), 'err').'.xlsx';
        Spreadsheet::writeXlsx($path, ['Baris', 'Kesalahan'], collect($import->errors ?? [])->map(fn ($messages, $line) => [$line, implode(' ', $messages)]));

        return response()->download($path, "kesalahan-impor-{$import->id}.xlsx")->deleteFileAfterSend();
    }

    private function discardFile(Import $import): void
    {
        Storage::disk('local')->delete($import->path);
        $import->delete();
    }
}
