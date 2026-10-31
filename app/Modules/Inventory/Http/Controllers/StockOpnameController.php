<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\StockOpname;
use App\Modules\Inventory\Services\StockCorrectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Lembar hitung fisik: isi jumlah nyata per batch, selisihnya dicatat sebagai mutasi opname. */
class StockOpnameController extends Controller
{
    public function create(Request $request): View
    {
        $term = $request->string('q')->trim()->value();

        $batches = Batch::with('drug')
            ->where('batches.status', Batch::STATUS_AVAILABLE)
            ->where('batches.qty_on_hand', '>', 0)
            ->whereHas('drug', fn ($q) => $q->search($term))
            ->join('drugs', 'drugs.id', '=', 'batches.drug_id')
            ->orderBy('drugs.name')->orderBy('batches.expiry_date')
            ->select('batches.*')
            ->get();

        return view('inventory.stock.opname', [
            'batches' => $batches,
            'history' => StockOpname::with('user')->latest('id')->limit(10)->get(),
        ]);
    }

    public function store(Request $request, StockCorrectionService $service): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'counts' => ['array'],
            'counts.*.system' => ['required', 'integer', 'min:0'],
            'counts.*.counted' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ], [], ['reason' => 'keterangan opname', 'counts.*.counted' => 'hasil hitung']);

        // Baris yang tidak diisi dianggap tidak dihitung pada sesi ini.
        $counts = collect($data['counts'] ?? [])
            ->filter(fn ($row) => isset($row['counted']) && $row['counted'] !== '')
            ->map(fn ($row) => ['system' => (int) $row['system'], 'counted' => (int) $row['counted']])
            ->all();

        if (! $counts) {
            return back()->withInput()->withErrors(['counts' => 'Isi hasil hitung fisik minimal satu batch.']);
        }

        $opname = $service->opname($counts, $data['reason'], $request->user());

        return redirect()->route('stock.opname')->with('success', $opname->batches_changed
            ? "Opname {$opname->number} tersimpan: {$opname->batches_counted} batch dihitung, {$opname->batches_changed} batch disesuaikan."
            : "Opname {$opname->number} tersimpan: {$opname->batches_counted} batch dihitung, semua cocok dengan sistem.");
    }
}
