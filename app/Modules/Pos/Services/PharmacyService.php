<?php

namespace App\Modules\Pos\Services;

use App\Modules\Cds\Services\CdsService;
use App\Modules\Emr\Enums\PrescriptionStatus;
use App\Modules\Emr\Models\Prescription;
use App\Modules\Identity\Models\User;
use App\Modules\Patient\Enums\EncounterStatus;
use App\Modules\Patient\Services\EncounterStateMachine;
use App\Modules\Pos\Exceptions\PosActionNotAllowed;
use App\Modules\Pos\Models\Sale;
use Illuminate\Support\Facades\DB;

/**
 * Verifikasi resep oleh apoteker (PRD §6.5 jalur resep poli): telaah resep,
 * setujui peringatan berat dengan alasan, lalu resep "Disiapkan" dan pasien
 * diteruskan ke pembayaran.
 */
class PharmacyService
{
    public function __construct(
        private CartService $cart,
        private CdsService $cds,
        private EncounterStateMachine $encounters,
    ) {}

    /** @param array<string, string|null> $overrideReasons */
    public function verify(Sale $sale, array $overrideReasons, User $pharmacist): Prescription
    {
        return DB::transaction(function () use ($sale, $overrideReasons, $pharmacist) {
            $this->cart->guardOpen($sale);

            $prescription = Prescription::lockForUpdate()->find($sale->prescription_id);
            if (! $prescription || $prescription->status !== PrescriptionStatus::PendingVerification) {
                throw new PosActionNotAllowed('Tidak ada resep yang menunggu verifikasi di keranjang ini.');
            }

            // Peringatan dihitung dari obat resep yang masih ada di keranjang (apoteker bisa mengeluarkan obat).
            $quote = $this->cart->quote($sale);
            $this->cds->recordOverrides($quote['pending_major'], $overrideReasons, $pharmacist, [
                'context' => 'dispense',
                'patient_id' => $sale->patient_id,
                'prescription_id' => $prescription->id,
                'sale_id' => $sale->id,
            ]);

            $prescription->forceFill([
                'status' => PrescriptionStatus::Prepared,
                'verified_at' => now(),
                'verified_by' => $pharmacist->id,
            ])->save();

            $encounter = $sale->encounter;
            if ($encounter && $encounter->status === EncounterStatus::WaitingPharmacy) {
                $this->encounters->transition($encounter, EncounterStatus::ReadyBilling);
            }

            return $prescription;
        });
    }
}
