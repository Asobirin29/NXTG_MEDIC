<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Exceptions\StockChanged;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockOpname;
use App\Support\Audit\AuditLogger;
use App\Support\Sequence\SequenceGenerator;
use Illuminate\Support\Facades\DB;

/**
 * Koreksi stok (PRD §6.4): buku besar tidak pernah diedit, selisih dicatat
 * sebagai mutasi baru beralasan — penyesuaian per batch atau stok opname.
 */
class StockCorrectionService
{
    public const ADJUSTMENT_REASONS = [
        'rusak' => 'Rusak / pecah',
        'kedaluwarsa' => 'Kedaluwarsa (dimusnahkan)',
        'hilang' => 'Hilang',
        'koreksi' => 'Koreksi salah input',
        'lainnya' => 'Lainnya',
    ];

    public function __construct(
        private StockLedger $ledger,
        private AuditLogger $audit,
        private SequenceGenerator $sequence,
    ) {}

    public function adjust(Batch $batch, int $delta, string $reasonCode, string $note, User $user): StockMovement
    {
        return DB::transaction(function () use ($batch, $delta, $reasonCode, $note, $user) {
            $locked = Batch::with('drug')->lockForUpdate()->findOrFail($batch->id);
            $reason = (self::ADJUSTMENT_REASONS[$reasonCode] ?? $reasonCode).' — '.trim($note);

            $movement = $this->ledger->record($locked, StockMovementType::Adjustment, $delta, $reason, null, $user);

            $this->audit->log('stock.adjusted', $locked,
                ['qty_on_hand' => $locked->qty_on_hand - $delta],
                ['qty_on_hand' => $locked->qty_on_hand, 'delta' => $delta],
                $reason);

            return $movement;
        });
    }

    /**
     * Simpan hasil hitung fisik. $counts: batch_id => [system => saldo saat form dibuka,
     * counted => hasil hitung]. Bila saldo berubah sejak form dibuka (ada pengeluaran
     * di tengah opname) seluruh opname dibatalkan agar selisihnya tidak salah hitung.
     *
     * @param  array<int, array{system: int, counted: int}>  $counts
     */
    public function opname(array $counts, string $reason, User $user): StockOpname
    {
        return DB::transaction(function () use ($counts, $reason, $user) {
            $batches = Batch::with('drug')->whereKey(array_keys($counts))->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $changed = $batches->filter(fn (Batch $b) => $b->qty_on_hand !== (int) $counts[$b->id]['system']);
            if ($changed->isNotEmpty()) {
                $names = $changed->map(fn ($b) => "{$b->drug->name} ({$b->batch_number})")->implode(', ');
                throw new StockChanged("Saldo berubah sejak lembar opname dibuka: {$names}. Muat ulang halaman lalu hitung ulang baris tersebut.");
            }

            $year = now()->year;
            $opname = StockOpname::create([
                'number' => sprintf('SO-%d-%04d', $year, $this->sequence->next($user->tenant_id, 'stock_opname-'.$year)),
                'user_id' => $user->id,
                'reason' => trim($reason),
                'batches_counted' => $batches->count(),
                'batches_changed' => 0,
            ]);

            $diffs = 0;
            foreach ($batches as $batch) {
                $delta = (int) $counts[$batch->id]['counted'] - $batch->qty_on_hand;
                if ($delta !== 0) {
                    $this->ledger->record($batch, StockMovementType::Opname, $delta, "Opname {$opname->number}: ".trim($reason), $opname, $user);
                    $diffs++;
                }
            }

            $opname->forceFill(['batches_changed' => $diffs])->save();
            $this->audit->log('stock.opname', $opname, null, ['counted' => $batches->count(), 'changed' => $diffs], trim($reason));

            return $opname;
        });
    }
}
