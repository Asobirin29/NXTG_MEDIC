<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Drug;
use App\Modules\Inventory\Services\PackagingConverter;
use App\Modules\Inventory\Services\StockCorrectionService;
use App\Modules\Inventory\Services\StockLevels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Ringkasan stok, peringatan, kartu stok per obat, dan penyesuaian per batch. */
class StockController extends Controller
{
    public const FILTERS = ['low' => 'Stok menipis', 'expiring' => 'ED ≤ 90 hari', 'expired' => 'Kedaluwarsa'];

    public function index(Request $request, StockLevels $levels): View
    {
        $filter = array_key_exists($request->query('filter'), self::FILTERS) ? $request->query('filter') : null;
        $term = $request->string('q')->trim()->value();

        $low = $levels->lowStock();
        $expiring = $levels->nearExpiry();
        $expired = $levels->expired();

        $view = ['filter' => $filter, 'filters' => self::FILTERS, 'counts' => [
            'low' => $low->count(), 'expiring' => $expiring->count(), 'expired' => $expired->count(),
        ]];

        if ($filter === 'expiring' || $filter === 'expired') {
            $batches = $filter === 'expiring' ? $expiring : $expired;

            return view('inventory.stock.index', $view + [
                'batches' => $term ? $batches->filter(fn ($b) => str_contains(mb_strtolower($b->drug->name.' '.$b->batch_number), mb_strtolower($term)))->values() : $batches,
            ]);
        }

        if ($filter === 'low') {
            $drugs = $term ? $low->filter(fn ($d) => str_contains(mb_strtolower($d->name), mb_strtolower($term)))->values() : $low;
        } else {
            $drugs = Drug::with('packagings')->active()->search($term)->orderBy('name')->paginate(25)->withQueryString();
            $available = $levels->available($drugs->pluck('id')->all());
            $drugs->each(fn (Drug $d) => $d->setAttribute('available_stock', $available[$d->id]));
        }

        // ED terdekat dari batch yang masih bisa dikeluarkan, per obat di halaman ini.
        $nearest = Batch::dispensable()->whereIn('drug_id', $drugs->pluck('id'))
            ->groupBy('drug_id')->selectRaw('drug_id, MIN(expiry_date) as nearest, COUNT(*) as batch_count')
            ->get()->keyBy('drug_id');

        return view('inventory.stock.index', $view + [
            'drugs' => $drugs,
            'nearest' => $nearest,
            'stockValue' => $filter ? null : (int) round((float) Batch::dispensable()->selectRaw('SUM(qty_on_hand * purchase_price_per_base) as v')->value('v')),
        ]);
    }

    public function show(Request $request, Drug $drug, StockLevels $levels): View
    {
        $drug->load(['packagings', 'substances']);
        $showEmpty = $request->boolean('kosong');

        $batches = $drug->batches()->with('supplier')
            ->when(! $showEmpty, fn ($q) => $q->where('qty_on_hand', '>', 0))
            ->orderBy('status')->fefo()->get();

        $batchFilter = $request->integer('batch') ?: null;
        $movements = $drug->movements()
            ->with(['batch', 'user', 'reference'])
            ->when($batchFilter, fn ($q, $id) => $q->where('batch_id', $id))
            ->latest('id')
            ->paginate(30)->withQueryString();

        return view('inventory.stock.show', [
            'drug' => $drug,
            'packs' => $drug->packagingRows(),
            'available' => $levels->available([$drug->id])[$drug->id],
            'batches' => $batches,
            'movements' => $movements,
            'batchFilter' => $batchFilter,
            'showEmpty' => $showEmpty,
            'reasons' => StockCorrectionService::ADJUSTMENT_REASONS,
        ]);
    }

    public function adjust(Request $request, Batch $batch, StockCorrectionService $service, PackagingConverter $converter): RedirectResponse
    {
        $batch->load('drug.packagings');

        $data = $request->validate([
            'direction' => ['required', Rule::in(['in', 'out'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'drug_packaging_id' => ['required', 'integer', Rule::in($batch->drug->packagings->pluck('id')->all())],
            'reason_code' => ['required', Rule::in(array_keys(StockCorrectionService::ADJUSTMENT_REASONS))],
            'note' => ['required', 'string', 'min:5', 'max:300'],
        ], [], ['quantity' => 'jumlah', 'drug_packaging_id' => 'kemasan', 'reason_code' => 'alasan', 'note' => 'keterangan']);

        $packaging = $batch->drug->packagings->firstWhere('id', (int) $data['drug_packaging_id']);
        $base = $converter->toBase((int) $data['quantity'], $packaging->quantity_in_base);
        $delta = $data['direction'] === 'in' ? $base : -$base;

        $movement = $service->adjust($batch, $delta, $data['reason_code'], $data['note'], $request->user());

        return redirect()->route('stock.show', $batch->drug)
            ->with('success', sprintf('Stok batch %s %s %d %s. Saldo sekarang %d %s.',
                $batch->batch_number, $delta > 0 ? 'ditambah' : 'dikurangi', abs($delta), $batch->drug->base_unit,
                $movement->balance_after, $batch->drug->base_unit));
    }
}
