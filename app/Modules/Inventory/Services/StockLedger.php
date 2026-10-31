<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Exceptions\InsufficientStock;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * Satu-satunya pintu perubahan saldo batch. Setiap perubahan qty_on_hand
 * disertai satu baris stock_movements di transaksi yang sama, sehingga saldo
 * selalu bisa direkonstruksi dari buku besar.
 */
class StockLedger
{
    /** Batch harus sudah dikunci (lockForUpdate) oleh pemanggil di transaksi yang sama. */
    public function record(
        Batch $batch,
        StockMovementType $type,
        int $delta,
        ?string $reason = null,
        ?Model $reference = null,
        ?User $user = null,
    ): StockMovement {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Mutasi stok harus berjalan di dalam transaksi database.');
        }
        if ($delta === 0) {
            throw new InvalidArgumentException('Mutasi stok tidak boleh nol.');
        }

        $balance = $batch->qty_on_hand + $delta;
        if ($balance < 0) {
            $name = $batch->drug?->name ?? 'obat';
            throw new InsufficientStock("Saldo batch {$batch->batch_number} ({$name}) hanya {$batch->qty_on_hand}; tidak bisa dikurangi ".abs($delta).'.');
        }

        $batch->forceFill(['qty_on_hand' => $balance])->save();

        return StockMovement::create([
            'batch_id' => $batch->id,
            'drug_id' => $batch->drug_id,
            'type' => $type,
            'quantity' => $delta,
            'balance_after' => $balance,
            'reason' => $reason,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'user_id' => $user?->id,
        ]);
    }

    /**
     * Keluarkan stok sesuai hasil FefoAllocator::allocate (dipakai Farmasi/POS).
     *
     * @param  list<array{batch: Batch, quantity: int}>  $allocation
     * @return list<StockMovement>
     */
    public function issue(array $allocation, ?Model $reference = null, ?User $user = null, ?string $reason = null): array
    {
        return array_map(
            fn ($row) => $this->record($row['batch'], StockMovementType::Dispense, -$row['quantity'], $reason, $reference, $user),
            $allocation,
        );
    }
}
