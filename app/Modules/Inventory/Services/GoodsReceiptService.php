<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Drug;
use App\Modules\Inventory\Models\GoodsReceipt;
use App\Modules\Inventory\Models\Supplier;
use App\Support\Sequence\SequenceGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penerimaan barang (PRD §6.4): kemasan yang diterima dikonversi ke satuan
 * terkecil, masuk ke batch (baru atau batch bernomor sama), dan dicatat di
 * buku besar sebagai mutasi "receipt".
 */
class GoodsReceiptService
{
    public function __construct(
        private PackagingConverter $converter,
        private StockLedger $ledger,
        private SequenceGenerator $sequence,
    ) {}

    /**
     * @param  array{supplier_id: int, invoice_number?: ?string, received_at: string, notes?: ?string}  $header
     * @param  list<array{drug_id: int, drug_packaging_id: int, quantity: int, batch_number: string, expiry_date: string, unit_price: int}>  $items
     */
    public function receive(array $header, array $items, User $user): GoodsReceipt
    {
        return DB::transaction(function () use ($header, $items, $user) {
            $supplier = Supplier::findOrFail($header['supplier_id']);
            $receivedAt = Carbon::parse($header['received_at']);

            $receipt = GoodsReceipt::create([
                'number' => $this->nextNumber($user->tenant_id, $receivedAt),
                'supplier_id' => $supplier->id,
                'invoice_number' => $header['invoice_number'] ?? null,
                'received_at' => $receivedAt,
                'received_by' => $user->id,
                'notes' => $header['notes'] ?? null,
            ]);

            $total = 0;
            foreach (array_values($items) as $i => $row) {
                $total += $this->receiveItem($receipt, $supplier, $row, $i, $user);
            }

            $receipt->forceFill(['total_cost' => $total])->saveQuietly();

            return $receipt;
        });
    }

    private function receiveItem(GoodsReceipt $receipt, Supplier $supplier, array $row, int $index, User $user): int
    {
        $drug = Drug::with('packagings')->findOrFail($row['drug_id']);
        $packaging = $drug->packagings->firstWhere('id', (int) $row['drug_packaging_id'])
            ?? throw ValidationException::withMessages(["items.{$index}.drug_packaging_id" => "Kemasan tidak sesuai untuk {$drug->name}."]);

        $quantityBase = $this->converter->toBase((int) $row['quantity'], $packaging->quantity_in_base);
        $batchNumber = mb_strtoupper(trim($row['batch_number']));
        $expiry = Carbon::parse($row['expiry_date'])->startOfDay();
        $unitPrice = (int) $row['unit_price'];
        $pricePerBase = $unitPrice / $packaging->quantity_in_base;

        $batch = Batch::where('drug_id', $drug->id)
            ->where('batch_number', $batchNumber)
            ->where('status', Batch::STATUS_AVAILABLE)
            ->lockForUpdate()
            ->first();

        if ($batch && ! $batch->expiry_date->isSameDay($expiry)) {
            throw ValidationException::withMessages([
                "items.{$index}.expiry_date" => "Batch {$batchNumber} {$drug->name} sudah tercatat dengan ED {$batch->expiry_date->format('d/m/Y')}. Periksa kembali nomor batch atau tanggal ED.",
            ]);
        }

        if ($batch) {
            // Batch bernomor sama diterima lagi: HPP dirata-rata tertimbang.
            $existingValue = $batch->qty_on_hand * (float) $batch->purchase_price_per_base;
            $newQty = $batch->qty_on_hand + $quantityBase;
            $batch->forceFill([
                'purchase_price_per_base' => $newQty > 0 ? ($existingValue + $quantityBase * $pricePerBase) / $newQty : $pricePerBase,
                'supplier_id' => $supplier->id,
            ])->save();
        } else {
            $batch = Batch::create([
                'drug_id' => $drug->id,
                'batch_number' => $batchNumber,
                'expiry_date' => $expiry,
                'purchase_price_per_base' => $pricePerBase,
                'supplier_id' => $supplier->id,
                'status' => Batch::STATUS_AVAILABLE,
                'qty_on_hand' => 0,
            ]);
        }

        $batch->setRelation('drug', $drug);
        $this->ledger->record($batch, StockMovementType::Receipt, $quantityBase, "Penerimaan {$receipt->number}", $receipt, $user);

        $receipt->items()->create([
            'drug_id' => $drug->id,
            'batch_id' => $batch->id,
            'drug_name' => $drug->name,
            'batch_number' => $batchNumber,
            'expiry_date' => $expiry,
            'unit_name' => $packaging->unit_name,
            'quantity_in_base_per_unit' => $packaging->quantity_in_base,
            'quantity' => (int) $row['quantity'],
            'quantity_base' => $quantityBase,
            'unit_price' => $unitPrice,
        ]);

        return (int) $row['quantity'] * $unitPrice;
    }

    /** PB-2026-0001: nomor urut per faskes per tahun. */
    private function nextNumber(int $tenantId, Carbon $date): string
    {
        $seq = $this->sequence->next($tenantId, 'goods_receipt-'.$date->year);

        return sprintf('PB-%d-%04d', $date->year, $seq);
    }
}
