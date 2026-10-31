<?php

namespace App\Modules\Emr\Services;

use App\Modules\Emr\Exceptions\EmrActionNotAllowed;
use App\Modules\Emr\Models\ClinicalNote;
use App\Modules\Emr\Models\ClinicalNoteAddendum;
use App\Modules\Emr\Models\EncounterDiagnosis;
use App\Modules\Emr\Models\EncounterService;
use App\Modules\Emr\Models\Icd10;
use App\Modules\Emr\Models\VitalSign;
use App\Modules\Facility\Models\MedicalService;
use App\Modules\Identity\Models\User;
use App\Modules\Patient\Enums\EncounterStatus;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Services\EncounterStateMachine;
use Illuminate\Support\Facades\DB;

/**
 * Alur RME satu kunjungan: asesmen perawat → pemeriksaan dokter (SOAP,
 * ICD-10, tindakan, e-Resep) → kunci rekam medis. Aturan "boleh diubah atau
 * tidak" ada di EncounterGuard supaya controller & layar lain tidak bisa melewatinya.
 */
class EmrService
{
    public function __construct(
        private EncounterStateMachine $machine,
        private EncounterGuard $guard,
        private PrescriptionService $prescriptions,
    ) {}

    /** Perawat/dokter mencatat tanda vital; kunjungan yang masih menunggu menjadi "Diperiksa Perawat". */
    public function recordVitals(Encounter $encounter, array $data, User $by): VitalSign
    {
        if (! in_array($encounter->status, [EncounterStatus::Queued, EncounterStatus::NurseAssessed, EncounterStatus::InConsultation], true)) {
            throw new EmrActionNotAllowed("Tanda vital tidak bisa dicatat untuk kunjungan berstatus \"{$encounter->status->label()}\".");
        }

        return DB::transaction(function () use ($encounter, $data, $by) {
            $vital = VitalSign::create($data + [
                'encounter_id' => $encounter->id,
                'recorded_by' => $by->id,
                'recorded_at' => now(),
            ]);

            if ($encounter->status === EncounterStatus::Queued) {
                $this->machine->transition($encounter, EncounterStatus::NurseAssessed);
            }

            return $vital;
        });
    }

    /** Dokter memanggil pasien: status → "Diperiksa Dokter", catatan SOAP draf dibuat. */
    public function startConsultation(Encounter $encounter, User $doctor): ClinicalNote
    {
        return DB::transaction(function () use ($encounter, $doctor) {
            if ($encounter->status !== EncounterStatus::InConsultation) {
                $this->machine->transition($encounter, EncounterStatus::InConsultation);
            }

            if (! $encounter->doctor_id) {
                $encounter->forceFill(['doctor_id' => $doctor->id])->saveQuietly();
            }

            return ClinicalNote::firstOrCreate(['encounter_id' => $encounter->id], ['doctor_id' => $doctor->id]);
        });
    }

    public function saveDraft(Encounter $encounter, array $soap): ClinicalNote
    {
        $note = $this->guard->editableNote($encounter);
        $note->update(array_intersect_key($soap, ClinicalNote::SECTIONS));

        return $note;
    }

    public function addDiagnosis(Encounter $encounter, Icd10 $icd, string $type, ?string $note = null): EncounterDiagnosis
    {
        $this->guard->editableNote($encounter);

        return DB::transaction(function () use ($encounter, $icd, $type, $note) {
            if ($encounter->diagnoses()->where('icd10_id', $icd->id)->exists()) {
                throw new EmrActionNotAllowed("Diagnosis {$icd->code} sudah ditambahkan.");
            }

            // Hanya satu diagnosis primer: primer lama otomatis menjadi sekunder.
            if ($type === 'primary') {
                $encounter->diagnoses()->where('type', 'primary')->get()->each->update(['type' => 'secondary']);
            } elseif ($encounter->diagnoses()->where('type', 'primary')->doesntExist()) {
                $type = 'primary';
            }

            return EncounterDiagnosis::create([
                'encounter_id' => $encounter->id, 'icd10_id' => $icd->id, 'type' => $type, 'note' => $note,
            ]);
        });
    }

    public function removeDiagnosis(Encounter $encounter, EncounterDiagnosis $diagnosis): void
    {
        $this->guard->editableNote($encounter);
        $this->ensureBelongs($encounter, $diagnosis->encounter_id);

        DB::transaction(function () use ($encounter, $diagnosis) {
            $wasPrimary = $diagnosis->isPrimary();
            $diagnosis->delete();

            // Diagnosis sekunder pertama naik menjadi primer.
            if ($wasPrimary) {
                $encounter->diagnoses()->where('type', 'secondary')->orderBy('id')->first()?->update(['type' => 'primary']);
            }
        });
    }

    public function addService(Encounter $encounter, MedicalService $service, int $quantity, User $by, ?string $note = null): EncounterService
    {
        $this->guard->editableNote($encounter);

        $price = $service->loadMissing('prices')->priceFor($encounter->payment_type_id);
        if ($price === null) {
            throw new EmrActionNotAllowed("Tarif {$service->name} untuk jenis bayar {$encounter->paymentType->name} belum diatur. Hubungi admin.");
        }

        return EncounterService::create([
            'encounter_id' => $encounter->id,
            'medical_service_id' => $service->id,
            'quantity' => $quantity,
            'unit_price' => $price,
            'note' => $note,
            'performed_by' => $by->id,
        ]);
    }

    public function removeService(Encounter $encounter, EncounterService $item): void
    {
        $this->guard->editableNote($encounter);
        $this->ensureBelongs($encounter, $item->encounter_id);
        $item->delete();
    }

    /**
     * Selesai periksa: simpan SOAP terakhir, validasi kelengkapan, kirim
     * e-Resep (peringatan berat wajib beralasan), kunci rekam medis, lalu
     * teruskan kunjungan: ada resep → "Menunggu Obat", tanpa resep → "Siap Bayar".
     *
     * @param  array<string, string|null>  $overrideReasons  alert key => alasan klinis
     */
    public function finish(Encounter $encounter, array $soap, User $doctor, array $overrideReasons = []): ClinicalNote
    {
        return DB::transaction(function () use ($encounter, $soap, $doctor, $overrideReasons) {
            $note = $this->saveDraft($encounter, $soap);

            $missing = [];
            if (blank($note->subjective)) {
                $missing[] = 'Subjective (keluhan)';
            }
            if ($encounter->diagnoses()->where('type', 'primary')->doesntExist()) {
                $missing[] = 'diagnosis primer ICD-10';
            }
            if ($missing) {
                throw new EmrActionNotAllowed('Belum bisa diselesaikan, lengkapi: '.implode(', ', $missing).'.');
            }

            $hasPrescription = $this->prescriptions->send($encounter, $overrideReasons, $doctor);

            $note->forceFill([
                'doctor_id' => $note->doctor_id ?? $doctor->id,
                'status' => 'locked',
                'locked_at' => now(),
                'locked_by' => $doctor->id,
            ])->save();

            $this->machine->transition($encounter, $hasPrescription ? EncounterStatus::WaitingPharmacy : EncounterStatus::ReadyBilling);

            return $note;
        });
    }

    public function addAddendum(Encounter $encounter, string $content, User $author): ClinicalNoteAddendum
    {
        $note = $encounter->clinicalNote;

        if (! $note?->isLocked()) {
            throw new EmrActionNotAllowed('Addendum hanya untuk rekam medis yang sudah dikunci. Ubah langsung catatan SOAP.');
        }

        return ClinicalNoteAddendum::create(['clinical_note_id' => $note->id, 'author_id' => $author->id, 'content' => $content]);
    }

    private function ensureBelongs(Encounter $encounter, int $encounterId): void
    {
        if ($encounter->id !== $encounterId) {
            abort(404);
        }
    }
}
