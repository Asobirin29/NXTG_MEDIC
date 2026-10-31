<?php

namespace App\Modules\Pos\Services;

use App\Modules\Emr\Enums\PrescriptionStatus;
use App\Modules\Emr\Models\Prescription;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\DrugCategory;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Patient\Enums\EncounterStatus;
use App\Modules\Patient\Services\EncounterStateMachine;
use App\Modules\Pos\Exceptions\PosActionNotAllowed;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\SaleItemBatch;
use App\Modules\Pos\Models\SaleReturn;
use App\Support\Audit\AuditLogger;
use App\Support\Sequence\SequenceGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Void (batalkan transaksi utuh) dan retur sebagian (PRD §6.5). Stok selalu
 * kembali ke batch asalnya; retur rusak masuk batch karantina yang tidak
 * pernah dialokasikan FEFO.
 */
class SaleReversalService
{
    public function __construct(
        private StockLedger $ledger,
        private EncounterStateMachine $encounters,
        private PharmacistApproval $approval,
        private SequenceGenerator $sequence,
        private AuditLogger $audit,
    ) {}

    /** Obat psikotropika & narkotika: void/retur wajib persetujuan apoteker. */
    public function containsRestricted(Sale $sale): bool
    {
        return $sale->items->contains(fn ($i) => $i->drug && in_array($i->drug->category, [DrugCategory::Psikotropika, DrugCategory::Narkotika], true));
    }

    public function void(Sale $sale, string $reason, User $user, ?int $approverId = null, ?string $approverPin = null): Sale
    {
        return DB::transaction(function () use ($sale, $reason, $user, $approverId, $approverPin) {
            $locked = Sale::with(['items.drug', 'items.batches', 'encounter'])->lockForUpdate()->findOrFail($sale->id);

            if (! $locked->isCompleted()) {
                throw new PosActionNotAllowed('Hanya transaksi lunas yang bisa di-void.');
            }
            if ($locked->returns()->exists()) {
                throw new PosActionNotAllowed('Transaksi ini sudah punya retur; void tidak bisa dilakukan. Gunakan retur untuk sisa obatnya.');
            }

            $approver = $this->containsRestricted($locked) ? $this->approval->resolve($user, $approverId, $approverPin) : null;

            foreach ($locked->items->flatMap->batches->sortBy('batch_id') as $alloc) {
                $batch = Batch::with('drug')->lockForUpdate()->findOrFail($alloc->batch_id);
                $this->ledger->record($batch, StockMovementType::Void, $alloc->quantity, "Void {$locked->number}: {$reason}", $locked, $user);
            }

            $locked->forceFill([
                'status' => Sale::VOIDED,
                'voided_at' => now(),
                'voided_by' => $user->id,
                'void_approved_by' => $approver?->id,
                'void_reason' => $reason,
            ])->save();

            // Tagihan kunjungan dibuka ulang agar bisa ditagih ulang dengan benar.
            if ($locked->prescription_id) {
                Prescription::whereKey($locked->prescription_id)->update(['status' => PrescriptionStatus::Prepared]);
            }
            if ($locked->encounter && $locked->encounter->status === EncounterStatus::Completed) {
                $this->encounters->transition($locked->encounter, EncounterStatus::ReadyBilling, "Void {$locked->number}: {$reason}");
            }

            $this->audit->log('sale.voided', $locked, null, ['approved_by' => $approver?->id], $reason);

            return $locked;
        });
    }

    /**
     * @param  array<int, array{quantity: int, condition: string}>  $lines  sale_item_batch_id => jumlah (satuan terkecil) & kondisi
     */
    public function returnItems(Sale $sale, array $lines, string $reason, User $user, ?int $approverId, ?string $approverPin): SaleReturn
    {
        // Retur selalu butuh PIN apoteker, termasuk bila apoteker sendiri yang memproses.
        $approver = $this->approval->verify($user, $approverId, $approverPin);

        return DB::transaction(function () use ($sale, $lines, $reason, $user, $approver) {
            $locked = Sale::with('paymentType')->lockForUpdate()->findOrFail($sale->id);
            if (! $locked->isCompleted()) {
                throw new PosActionNotAllowed('Retur hanya untuk transaksi lunas.');
            }

            $lines = array_filter($lines, fn ($l) => (int) ($l['quantity'] ?? 0) > 0);
            if (! $lines) {
                throw ValidationException::withMessages(['lines' => 'Isi jumlah yang diretur minimal satu baris.']);
            }

            $allocs = SaleItemBatch::with(['saleItem', 'batch.drug'])->whereKey(array_keys($lines))->orderBy('batch_id')->lockForUpdate()->get()->keyBy('id');

            $return = SaleReturn::create([
                'number' => sprintf('RTR-%d-%04d', now()->year, $this->sequence->next($user->tenant_id, 'sale_return-'.now()->year)),
                'sale_id' => $locked->id,
                'user_id' => $user->id,
                'approved_by' => $approver->id,
                'reason' => $reason,
            ]);

            $refundTotal = 0;
            foreach ($lines as $allocId => $line) {
                $alloc = $allocs[$allocId] ?? null;
                if (! $alloc || $alloc->saleItem->sale_id !== $locked->id) {
                    abort(404);
                }

                $qty = (int) $line['quantity'];
                if ($qty > $alloc->quantity - $alloc->returned) {
                    throw ValidationException::withMessages(["lines.{$allocId}.quantity" => "Retur {$alloc->saleItem->name} batch {$alloc->batch->batch_number} melebihi sisa yang bisa diretur (".($alloc->quantity - $alloc->returned).').']);
                }

                $condition = $line['condition'] === 'quarantine' ? 'quarantine' : 'sellable';
                $target = $condition === 'sellable' ? Batch::with('drug')->lockForUpdate()->findOrFail($alloc->batch_id) : $this->quarantineBatch($alloc->batch);
                $type = $condition === 'sellable' ? StockMovementType::Return : StockMovementType::Quarantine;
                $this->ledger->record($target, $type, $qty, "Retur {$return->number}: {$reason}", $return, $user);

                $refund = $locked->paymentType->patient_pays ? $qty * $alloc->saleItem->unit_price : 0;
                $refundTotal += $refund;

                $return->items()->create([
                    'sale_item_id' => $alloc->sale_item_id, 'sale_item_batch_id' => $alloc->id, 'batch_id' => $target->id,
                    'quantity' => $qty, 'condition' => $condition, 'refund' => $refund,
                ]);
                $alloc->increment('returned', $qty);
                $alloc->saleItem->increment('returned_base', $qty);
            }

            $return->forceFill(['refund_amount' => $refundTotal])->save();

            return $return;
        });
    }

    /** Batch karantina pasangan batch asal (nomor & ED sama, status quarantine). */
    private function quarantineBatch(Batch $source): Batch
    {
        $batch = Batch::with('drug')->where('drug_id', $source->drug_id)->where('batch_number', $source->batch_number)
            ->where('status', Batch::STATUS_QUARANTINE)->lockForUpdate()->first();

        return $batch ?? Batch::create([
            'drug_id' => $source->drug_id,
            'batch_number' => $source->batch_number,
            'expiry_date' => $source->expiry_date,
            'purchase_price_per_base' => $source->purchase_price_per_base,
            'supplier_id' => $source->supplier_id,
            'status' => Batch::STATUS_QUARANTINE,
            'qty_on_hand' => 0,
        ])->setRelation('drug', $source->drug);
    }
}
