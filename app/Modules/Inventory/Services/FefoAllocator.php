<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Exceptions\InsufficientStock;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Drug;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Alokasi First-Expired-First-Out (PRD §6.4, §10.4): ambil batch dengan
 * kedaluwarsa terdekat lebih dulu, boleh lintas batch, satuan terkecil.
 * Batch kedaluwarsa, karantina, atau kosong tidak pernah dialokasikan.
 */
class FefoAllocator
{
    /**
     * Rencana alokasi murni (tanpa database) dari daftar batch.
     *
     * @param  iterable<Batch>  $batches
     * @return list<array{batch: Batch, quantity: int}>
     */
    public function plan(iterable $batches, int $quantity, string $drugLabel = 'obat', ?Carbon $today = null): array
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Jumlah yang dialokasikan minimal 1.');
        }

        $eligible = collect($batches)
            ->filter(fn (Batch $b) => ! $b->isQuarantined() && $b->qty_on_hand > 0 && ! $b->isExpired($today))
            ->sortBy([fn ($a, $b) => $a->expiry_date <=> $b->expiry_date, fn ($a, $b) => $a->id <=> $b->id])
            ->values();

        $available = $eligible->sum('qty_on_hand');
        if ($available < $quantity) {
            throw new InsufficientStock("Stok {$drugLabel} tidak cukup: diminta {$quantity}, tersedia {$available} (batch kedaluwarsa tidak dihitung).");
        }

        $allocation = [];
        $remaining = $quantity;
        foreach ($eligible as $batch) {
            $take = min($remaining, $batch->qty_on_hand);
            $allocation[] = ['batch' => $batch, 'quantity' => $take];
            $remaining -= $take;
            if ($remaining === 0) {
                break;
            }
        }

        return $allocation;
    }

    /**
     * Kunci batch obat ini (SELECT … FOR UPDATE, urut ED) lalu alokasikan.
     * Wajib dipanggil di dalam transaksi agar kunci bertahan sampai mutasi dicatat.
     *
     * @return list<array{batch: Batch, quantity: int}>
     */
    public function allocate(Drug $drug, int $quantity): array
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Alokasi FEFO harus berjalan di dalam transaksi database.');
        }

        $batches = Batch::where('drug_id', $drug->id)->dispensable()->fefo()->lockForUpdate()->get();

        return $this->plan($batches, $quantity, $drug->name);
    }
}
