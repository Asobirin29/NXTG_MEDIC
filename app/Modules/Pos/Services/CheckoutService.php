<?php

namespace App\Modules\Pos\Services;

use App\Modules\Cds\Services\CdsService;
use App\Modules\Emr\Enums\PrescriptionStatus;
use App\Modules\Emr\Models\Prescription;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\FefoAllocator;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Patient\Enums\EncounterStatus;
use App\Modules\Patient\Services\EncounterStateMachine;
use App\Modules\Pos\Exceptions\PosActionNotAllowed;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\SaleItem;
use App\Support\Sequence\SequenceGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Checkout atomik (PRD §10.4): satu transaksi database untuk validasi
 * keranjang, persetujuan peringatan berat, kunci batch FEFO, mutasi stok,
 * pembekuan harga & HPP, pembayaran, dan penutupan kunjungan.
 *
 * Klik ganda aman: baris keranjang dikunci FOR UPDATE, sehingga permintaan
 * kedua menunggu lalu mendapati keranjang sudah lunas dan dikembalikan apa
 * adanya tanpa memotong stok lagi.
 */
class CheckoutService
{
    public function __construct(
        private CartService $cart,
        private CdsService $cds,
        private FefoAllocator $fefo,
        private StockLedger $ledger,
        private EncounterStateMachine $encounters,
        private SequenceGenerator $sequence,
        private PharmacistApproval $approval,
    ) {}

    /**
     * @param  array{payment_method?: ?string, paid_amount?: ?int, idempotency_key: string, overrides?: array<string, ?string>, approver_id?: ?int, approver_pin?: ?string}  $input
     */
    public function checkout(Sale $sale, array $input, User $cashier): Sale
    {
        return DB::transaction(function () use ($sale, $input, $cashier) {
            /** @var Sale $locked */
            $locked = Sale::with(['items.drug.prices', 'items.drug.packagings', 'paymentType', 'patient', 'prescription', 'encounter'])
                ->lockForUpdate()->findOrFail($sale->id);

            if ($locked->isCompleted() && $locked->idempotency_key === $input['idempotency_key']) {
                return $locked; // permintaan ganda dari klik yang sama
            }
            $this->cart->guardOpen($locked);

            $quote = $this->cart->quote($locked);
            if ($quote['problems']) {
                throw new PosActionNotAllowed(implode(' ', $quote['problems']));
            }

            // Peringatan berat yang belum disetujui: alasan + persetujuan apoteker.
            if ($quote['pending_major']) {
                $approver = $this->approval->resolve($cashier, $input['approver_id'] ?? null, $input['approver_pin'] ?? null);
                $this->cds->recordOverrides($quote['pending_major'], $input['overrides'] ?? [], $approver, [
                    'context' => 'pos',
                    'patient_id' => $locked->patient_id,
                    'prescription_id' => $locked->prescription_id,
                    'sale_id' => $locked->id,
                ]);
            }

            // Kunci & potong batch per obat, urut drug_id agar dua kasir tidak saling deadlock.
            $costTotal = 0.0;
            foreach ($locked->items->where('kind', 'drug')->sortBy('drug_id') as $item) {
                $costTotal += $this->dispense($locked, $item, $quote['lines'][$item->id]['unit_price'], $cashier);
            }
            foreach ($locked->items->where('kind', 'service') as $item) {
                $item->forceFill(['line_total' => $quote['lines'][$item->id]['line_total']])->save();
            }

            [$method, $paid, $change] = $this->payment($quote['amount_due'], $input);

            $locked->forceFill([
                'status' => Sale::COMPLETED,
                'number' => $this->nextNumber($cashier->tenant_id),
                'total_value' => $quote['total_value'],
                'amount_due' => $quote['amount_due'],
                'paid_amount' => $paid,
                'change_amount' => $change,
                'payment_method' => $method,
                'cost_total' => round($costTotal, 2),
                'idempotency_key' => $input['idempotency_key'],
                'cashier_id' => $cashier->id,
                'completed_at' => now(),
            ])->save();

            if ($locked->prescription_id) {
                Prescription::whereKey($locked->prescription_id)->update(['status' => PrescriptionStatus::Dispensed]);
            }
            if ($locked->encounter && $locked->encounter->status === EncounterStatus::ReadyBilling) {
                $this->encounters->transition($locked->encounter, EncounterStatus::Completed);
            }

            return $locked;
        });
    }

    /** @return float HPP item ini */
    private function dispense(Sale $sale, SaleItem $item, int $pricePerBase, User $cashier): float
    {
        $allocation = $this->fefo->allocate($item->drug, $item->quantity_base);
        $this->ledger->issue($allocation, $sale, $cashier, $sale->isEncounter() ? 'Penyerahan obat kunjungan' : 'Penjualan bebas (OTC)');

        $cost = 0.0;
        foreach ($allocation as $row) {
            $unitCost = (float) $row['batch']->purchase_price_per_base;
            $cost += $unitCost * $row['quantity'];
            $item->batches()->create(['batch_id' => $row['batch']->id, 'quantity' => $row['quantity'], 'unit_cost' => $unitCost]);
        }

        $item->forceFill([
            'unit_price' => $pricePerBase,
            'line_total' => $pricePerBase * $item->quantity_base,
            'cost_total' => round($cost, 2),
        ])->save();

        return $cost;
    }

    /** @return array{0: ?string, 1: int, 2: int} metode, dibayar, kembalian */
    private function payment(int $due, array $input): array
    {
        if ($due === 0) {
            return [null, 0, 0]; // BPJS/Gratis: tidak ada tagihan ke pasien
        }

        $method = $input['payment_method'] ?? null;
        if (! in_array($method, ['cash', 'non_cash'], true)) {
            throw ValidationException::withMessages(['payment_method' => 'Pilih metode pembayaran.']);
        }
        if ($method === 'non_cash') {
            return [$method, $due, 0];
        }

        $paid = (int) ($input['paid_amount'] ?? 0);
        if ($paid < $due) {
            throw ValidationException::withMessages(['paid_amount' => 'Uang diterima kurang dari total tagihan Rp'.number_format($due, 0, ',', '.').'.']);
        }

        return [$method, $paid, $paid - $due];
    }

    /** TRX-20261030-0001: nomor urut per faskes per hari. */
    private function nextNumber(int $tenantId): string
    {
        $day = now()->format('Ymd');

        return sprintf('TRX-%s-%04d', $day, $this->sequence->next($tenantId, 'sale-'.$day));
    }
}
