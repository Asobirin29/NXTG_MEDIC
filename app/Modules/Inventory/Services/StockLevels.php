<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Drug;
use Illuminate\Support\Collection;

/**
 * Sisa stok per obat (satuan terkecil) untuk layar resep, kasir, dan
 * peringatan. "Tersedia" hanya menghitung batch layak jual yang belum
 * kedaluwarsa — sama dengan yang bisa dialokasikan FEFO.
 */
class StockLevels
{
    /**
     * @param  list<int>  $drugIds
     * @return array<int, int> drug_id => stok tersedia
     */
    public function available(array $drugIds): array
    {
        if (! $drugIds) {
            return [];
        }

        $sums = Batch::dispensable()
            ->whereIn('drug_id', $drugIds)
            ->groupBy('drug_id')
            ->selectRaw('drug_id, SUM(qty_on_hand) as qty')
            ->pluck('qty', 'drug_id');

        return collect($drugIds)->mapWithKeys(fn ($id) => [$id => (int) ($sums[$id] ?? 0)])->all();
    }

    /** Obat aktif yang stok tersedianya ≤ ambang minimum, paling kritis dulu. */
    public function lowStock(): Collection
    {
        $drugs = Drug::with('packagings')->active()->orderBy('name')->get();
        $levels = $this->available($drugs->pluck('id')->all());

        return $drugs
            ->filter(fn (Drug $d) => $levels[$d->id] <= $d->min_stock)
            ->each(fn (Drug $d) => $d->setAttribute('available_stock', $levels[$d->id]))
            ->sortBy(fn (Drug $d) => $d->min_stock > 0 ? $d->available_stock / $d->min_stock : -1)
            ->values();
    }

    /** Batch layak jual bersaldo yang ED-nya ≤ 90 hari lagi (belum lewat). */
    public function nearExpiry(): Collection
    {
        return Batch::with('drug.packagings')
            ->where('status', Batch::STATUS_AVAILABLE)
            ->where('qty_on_hand', '>', 0)
            ->whereDate('expiry_date', '>', today())
            ->whereDate('expiry_date', '<=', today()->addDays(Batch::NEAR_EXPIRY_DAYS))
            ->fefo()->get();
    }

    /** Batch kedaluwarsa yang masih bersaldo: terblokir, perlu dimusnahkan lewat penyesuaian. */
    public function expired(): Collection
    {
        return Batch::with('drug.packagings')
            ->where('status', Batch::STATUS_AVAILABLE)
            ->where('qty_on_hand', '>', 0)
            ->whereDate('expiry_date', '<=', today())
            ->fefo()->get();
    }
}
