<?php

namespace App\Modules\Patient\Services;

use App\Modules\Facility\Models\PaymentType;
use App\Modules\Facility\Models\Poli;
use App\Modules\Identity\Models\User;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use App\Support\Sequence\SequenceGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Mendaftarkan pasien ke antrean poli (PRD §6.1). */
class RegistrationService
{
    public function __construct(private SequenceGenerator $sequences) {}

    public function register(Patient $patient, Poli $poli, PaymentType $paymentType, ?User $doctor, User $registrar): Encounter
    {
        if (! $patient->is_active) {
            throw ValidationException::withMessages(['patient_id' => 'Pasien nonaktif tidak bisa didaftarkan.']);
        }

        return DB::transaction(function () use ($patient, $poli, $paymentType, $doctor, $registrar) {
            $today = today();

            // Kunci baris pasien supaya dua loket tidak mendaftarkan pasien yang sama bersamaan.
            Patient::whereKey($patient->id)->lockForUpdate()->first();

            $open = Encounter::with('poli')
                ->where('patient_id', $patient->id)
                ->where('poli_id', $poli->id)
                ->onDate($today)
                ->open()
                ->first();

            if ($open) {
                throw ValidationException::withMessages([
                    'poli_id' => "Pasien sudah ada di antrean {$poli->name} hari ini (nomor {$open->queueLabel()}).",
                ]);
            }

            $number = $this->sequences->next($patient->tenant_id, "queue:{$poli->id}:{$today->format('Ymd')}");

            return Encounter::create([
                'patient_id' => $patient->id,
                'poli_id' => $poli->id,
                'doctor_id' => $doctor?->id,
                'payment_type_id' => $paymentType->id,
                'queue_date' => $today,
                'queue_number' => $number,
                'status' => 'QUEUED',
                'registered_by' => $registrar->id,
                'registered_at' => now(),
            ]);
        });
    }
}
