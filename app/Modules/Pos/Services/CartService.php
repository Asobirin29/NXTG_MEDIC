<?php

namespace App\Modules\Pos\Services;

use App\Modules\Cds\Models\AlertOverride;
use App\Modules\Cds\Services\CdsService;
use App\Modules\Emr\Enums\PrescriptionStatus;
use App\Modules\Facility\Models\PaymentType;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Exceptions\InsufficientStock;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Drug;
use App\Modules\Inventory\Models\DrugPackaging;
use App\Modules\Inventory\Services\FefoAllocator;
use App\Modules\Inventory\Services\PackagingConverter;
use App\Modules\Patient\Enums\EncounterStatus;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Pos\Exceptions\PosActionNotAllowed;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\SaleItem;
use Illuminate\Support\Facades\DB;

/**
 * Keranjang kasir/farmasi. Isi keranjang hanya jumlah; harga sesuai jenis
 * bayar dan rencana batch FEFO dihitung ulang setiap kali ditampilkan, lalu
 * dibekukan oleh CheckoutService saat Bayar (PRD §6.5 hold/resume).
 */
class CartService
{
    public function __construct(
        private PackagingConverter $converter,
        private FefoAllocator $fefo,
        private CdsService $cds,
    ) {}

    /** Buka (atau lanjutkan) keranjang kunjungan: resep dokter + tindakan poli. */
    public function openForEncounter(Encounter $encounter, User $user): Sale
    {
        return DB::transaction(function () use ($encounter, $user) {
            $encounter = Encounter::with(['prescription.items', 'services.service'])->lockForUpdate()->findOrFail($encounter->id);

            if (! in_array($encounter->status, [EncounterStatus::WaitingPharmacy, EncounterStatus::ReadyBilling], true)) {
                throw new PosActionNotAllowed("Kunjungan berstatus \"{$encounter->status->label()}\" belum/tidak lagi bisa ditagih.");
            }

            if ($existing = Sale::open()->where('encounter_id', $encounter->id)->first()) {
                return $existing;
            }

            $prescription = $encounter->prescription;
            $hasRx = $prescription && in_array($prescription->status, [PrescriptionStatus::PendingVerification, PrescriptionStatus::Prepared], true);

            $sale = Sale::create([
                'type' => 'encounter',
                'encounter_id' => $encounter->id,
                'prescription_id' => $hasRx ? $prescription->id : null,
                'patient_id' => $encounter->patient_id,
                'payment_type_id' => $encounter->payment_type_id,
                'created_by' => $user->id,
            ]);

            foreach ($hasRx ? $prescription->items : [] as $rx) {
                $sale->items()->create([
                    'kind' => 'drug', 'drug_id' => $rx->drug_id, 'drug_packaging_id' => $rx->drug_packaging_id, 'prescription_item_id' => $rx->id,
                    'name' => $rx->drug_name, 'unit_name' => $rx->unit_name, 'quantity_in_base_per_unit' => $rx->quantity_in_base_per_unit,
                    'quantity' => $rx->quantity, 'quantity_base' => $rx->quantity_base, 'signa' => $rx->signa,
                ]);
            }

            // Tarif tindakan sudah dibekukan saat dokter mencatatnya (sesuai jenis bayar kunjungan).
            foreach ($encounter->services as $service) {
                $sale->items()->create([
                    'kind' => 'service', 'encounter_service_id' => $service->id, 'name' => $service->service->name,
                    'quantity' => $service->quantity, 'quantity_base' => $service->quantity, 'unit_price' => $service->unit_price,
                ]);
            }

            return $sale;
        });
    }

    public function openOtc(User $user, ?string $label = null): Sale
    {
        $paymentType = PaymentType::active()->where('patient_pays', true)->ordered()->first() ?? PaymentType::active()->ordered()->firstOrFail();

        return Sale::create(['type' => 'otc', 'payment_type_id' => $paymentType->id, 'label' => $label, 'created_by' => $user->id]);
    }

    public function addDrug(Sale $sale, Drug $drug, DrugPackaging $packaging, int $quantity, ?string $signa): SaleItem
    {
        $this->guardOpen($sale);

        if (! $drug->is_active) {
            throw new PosActionNotAllowed("{$drug->name} sedang nonaktif.");
        }
        if ($packaging->drug_id !== $drug->id) {
            abort(404);
        }
        if ($drug->prices()->where('payment_type_id', $sale->payment_type_id)->doesntExist()) {
            throw new PosActionNotAllowed("Harga {$drug->name} untuk jenis bayar {$sale->paymentType->name} belum diatur di master obat.");
        }

        // Kemasan sama yang ditambahkan lagi (mis. scan dua kali) → jumlah digabung.
        $existing = $sale->items()->where('drug_packaging_id', $packaging->id)->whereNull('prescription_item_id')->first();
        if ($existing) {
            $newQty = $existing->quantity + $quantity;
            $existing->update(['quantity' => $newQty, 'quantity_base' => $this->converter->toBase($newQty, $packaging->quantity_in_base)] + ($signa ? ['signa' => $signa] : []));

            return $existing;
        }

        return $sale->items()->create([
            'kind' => 'drug', 'drug_id' => $drug->id, 'drug_packaging_id' => $packaging->id,
            'name' => $drug->name, 'unit_name' => $packaging->unit_name, 'quantity_in_base_per_unit' => $packaging->quantity_in_base,
            'quantity' => $quantity, 'quantity_base' => $this->converter->toBase($quantity, $packaging->quantity_in_base),
            'signa' => $signa ? trim($signa) : null,
        ]);
    }

    public function removeItem(Sale $sale, SaleItem $item, User $user): void
    {
        $this->guardOpen($sale);
        abort_unless($item->sale_id === $sale->id, 404);

        if ($item->kind === 'service') {
            throw new PosActionNotAllowed('Tindakan berasal dari ruang periksa; perubahan dilakukan dokter lewat addendum/koreksi tagihan.');
        }
        if ($item->isFromPrescription() && ! $user->hasRole(Role::Apoteker)) {
            throw new PosActionNotAllowed('Obat dari resep dokter hanya bisa dikeluarkan dari keranjang oleh apoteker.');
        }

        $item->delete();
    }

    /** Detail keranjang OTC: pasien (untuk cek alergi), jenis bayar, rujukan resep luar. */
    public function updateDetails(Sale $sale, array $data): void
    {
        $this->guardOpen($sale);

        $allowed = $sale->isEncounter() ? ['external_rx_ref', 'label'] : ['external_rx_ref', 'label', 'patient_id', 'payment_type_id'];
        $changes = array_intersect_key($data, array_flip($allowed));
        if (array_key_exists('payment_type_id', $changes) && empty($changes['payment_type_id'])) {
            unset($changes['payment_type_id']);
        }

        $sale->update($changes);
    }

    public function discard(Sale $sale): void
    {
        $this->guardOpen($sale);
        $sale->items()->delete();
        $sale->delete();
    }

    /**
     * Rincian harga, rencana batch FEFO (tanpa kunci), peringatan klinis,
     * dan hal yang masih menghalangi pembayaran.
     */
    public function quote(Sale $sale): array
    {
        $sale->loadMissing(['items.drug.prices', 'items.drug.packagings', 'paymentType', 'patient', 'prescription']);
        $drugItems = $sale->items->where('kind', 'drug');

        $batches = Batch::with('drug')->dispensable()->whereIn('drug_id', $drugItems->pluck('drug_id')->unique())->fefo()->get()->groupBy('drug_id');
        $used = []; // batch_id => jumlah yang sudah direncanakan untuk item sebelumnya

        $lines = [];
        $problems = [];
        $total = 0;

        foreach ($sale->items as $item) {
            if ($item->kind === 'service') {
                $lineTotal = $item->quantity * $item->unit_price;
                $lines[$item->id] = ['unit_price' => $item->unit_price, 'line_total' => $lineTotal, 'allocation' => [], 'shortage' => null];
                $total += $lineTotal;

                continue;
            }

            $price = $item->drug->priceFor($sale->payment_type_id);
            if ($price === null) {
                $problems[] = "Harga {$item->name} untuk {$sale->paymentType->name} belum diatur.";
            }
            $lineTotal = (int) $price * $item->quantity_base;
            $total += $lineTotal;

            $pool = ($batches[$item->drug_id] ?? collect())->map(function (Batch $b) use ($used) {
                $copy = clone $b;
                $copy->qty_on_hand = $b->qty_on_hand - ($used[$b->id] ?? 0);

                return $copy;
            });

            $allocation = [];
            $shortage = null;
            try {
                foreach ($this->fefo->plan($pool, $item->quantity_base, $item->name) as $row) {
                    $used[$row['batch']->id] = ($used[$row['batch']->id] ?? 0) + $row['quantity'];
                    $allocation[] = ['batch_number' => $row['batch']->batch_number, 'expiry_date' => $row['batch']->expiry_date, 'quantity' => $row['quantity']];
                }
            } catch (InsufficientStock $e) {
                $shortage = $e->getMessage();
                $problems[] = $shortage;
            }

            $lines[$item->id] = ['unit_price' => $price, 'line_total' => $lineTotal, 'allocation' => $allocation, 'shortage' => $shortage];
        }

        $alerts = $this->cds->check($sale->patient, $drugItems->pluck('drug_id'));
        $approved = $this->approvedAlertKeys($sale);
        $pendingMajor = array_values(array_filter($alerts, fn ($a) => $a->requiresOverride() && ! in_array($a->key, $approved, true)));

        $needsRxRef = $drugItems->contains(fn (SaleItem $i) => ! $i->isFromPrescription() && $i->drug->category->requiresPrescription());
        if ($needsRxRef && blank($sale->external_rx_ref)) {
            $problems[] = 'Ada obat keras/psikotropika/narkotika di luar resep poli: isi rujukan resep luar (dokter & nomor/tanggal resep).';
        }

        $awaitingVerification = $sale->prescription && $sale->prescription->status === PrescriptionStatus::PendingVerification;
        if ($awaitingVerification) {
            $problems[] = 'Resep dokter belum diverifikasi apoteker.';
        }
        if ($sale->items->isEmpty()) {
            $problems[] = 'Keranjang masih kosong.';
        }

        return [
            'lines' => $lines,
            'total_value' => $total,
            'amount_due' => $sale->paymentType->patient_pays ? $total : 0,
            'alerts' => $alerts,
            'approved_keys' => $approved,
            'pending_major' => $pendingMajor,
            'needs_rx_ref' => $needsRxRef,
            'awaiting_verification' => $awaitingVerification,
            'problems' => $problems,
        ];
    }

    /**
     * Kunci peringatan berat yang sudah disetujui apoteker: saat verifikasi
     * resep (dispense) atau sebelumnya di keranjang ini (pos).
     *
     * @return list<string>
     */
    public function approvedAlertKeys(Sale $sale): array
    {
        return AlertOverride::query()
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('context', 'pos')->where('sale_id', $sale->id))
                ->when($sale->prescription_id, fn ($q) => $q->orWhere(fn ($q) => $q->where('context', 'dispense')->where('prescription_id', $sale->prescription_id))))
            ->pluck('alert_key')->unique()->values()->all();
    }

    public function guardOpen(Sale $sale): void
    {
        if (! $sale->isOpen()) {
            throw new PosActionNotAllowed("Transaksi {$sale->number} sudah {$sale->statusLabel()}; keranjang tidak bisa diubah.");
        }
    }
}
