<?php

namespace App\Modules\Emr\Services;

use App\Modules\Cds\Services\CdsService;
use App\Modules\Cds\Support\ClinicalAlert;
use App\Modules\Emr\Enums\PrescriptionStatus;
use App\Modules\Emr\Exceptions\EmrActionNotAllowed;
use App\Modules\Emr\Models\Prescription;
use App\Modules\Emr\Models\PrescriptionItem;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Drug;
use App\Modules\Inventory\Models\DrugPackaging;
use App\Modules\Inventory\Services\PackagingConverter;
use App\Modules\Patient\Models\Encounter;
use Illuminate\Support\Facades\DB;

/** e-Resep dokter (PRD §6.2): disusun selama pemeriksaan, dikirim ke farmasi saat selesai. */
class PrescriptionService
{
    public function __construct(
        private EncounterGuard $guard,
        private PackagingConverter $converter,
        private CdsService $cds,
    ) {}

    public function addItem(Encounter $encounter, Drug $drug, DrugPackaging $packaging, int $quantity, string $signa, ?string $note, User $doctor): PrescriptionItem
    {
        $this->guard->editableNote($encounter);

        if (! $drug->is_active) {
            throw new EmrActionNotAllowed("{$drug->name} sedang nonaktif.");
        }
        if ($packaging->drug_id !== $drug->id) {
            abort(404);
        }

        return DB::transaction(function () use ($encounter, $drug, $packaging, $quantity, $signa, $note, $doctor) {
            $prescription = $this->draftFor($encounter, $doctor);

            if ($prescription->items()->where('drug_id', $drug->id)->exists()) {
                throw new EmrActionNotAllowed("{$drug->name} sudah ada di resep. Hapus dulu bila ingin mengubah jumlah atau aturan pakai.");
            }

            return PrescriptionItem::create([
                'prescription_id' => $prescription->id,
                'drug_id' => $drug->id,
                'drug_packaging_id' => $packaging->id,
                'drug_name' => $drug->name,
                'unit_name' => $packaging->unit_name,
                'quantity_in_base_per_unit' => $packaging->quantity_in_base,
                'quantity' => $quantity,
                'quantity_base' => $this->converter->toBase($quantity, $packaging->quantity_in_base),
                'signa' => trim($signa),
                'note' => $note,
            ]);
        });
    }

    public function removeItem(Encounter $encounter, PrescriptionItem $item): void
    {
        $this->guard->editableNote($encounter);

        if ($item->prescription?->encounter_id !== $encounter->id) {
            abort(404);
        }

        $item->delete();
    }

    /** "Salin Resep Kunjungan Terakhir" (PRD §11.1). Obat nonaktif atau yang sudah ada dilewati. */
    public function copyLast(Encounter $encounter, User $doctor): array
    {
        $this->guard->editableNote($encounter);

        $last = Prescription::with('items.drug')
            ->where('patient_id', $encounter->patient_id)
            ->where('encounter_id', '!=', $encounter->id)
            ->whereNotIn('status', [PrescriptionStatus::Draft, PrescriptionStatus::Cancelled])
            ->latest('sent_at')
            ->first();

        if (! $last) {
            throw new EmrActionNotAllowed('Pasien belum punya resep sebelumnya.');
        }

        $copied = 0;
        $skipped = [];

        foreach ($last->items as $old) {
            $packaging = $old->drug_packaging_id ? DrugPackaging::find($old->drug_packaging_id) : null;

            try {
                if (! $old->drug || ! $packaging) {
                    throw new EmrActionNotAllowed("{$old->drug_name} tidak lagi tersedia.");
                }
                $this->addItem($encounter, $old->drug, $packaging, $old->quantity, $old->signa, $old->note, $doctor);
                $copied++;
            } catch (EmrActionNotAllowed $e) {
                $skipped[] = $old->drug_name;
            }
        }

        return ['copied' => $copied, 'skipped' => $skipped, 'date' => $last->sent_at];
    }

    /** @return list<ClinicalAlert> */
    public function alerts(Encounter $encounter): array
    {
        $prescription = $encounter->prescription()->with('items')->first();

        return $prescription ? $this->cds->check($encounter->patient, $prescription->items->pluck('drug_id')) : [];
    }

    /**
     * Dipanggil saat dokter menyelesaikan pemeriksaan. Peringatan berat wajib
     * punya alasan; resep berpindah ke "Menunggu Verifikasi" farmasi.
     *
     * @return bool true bila ada resep yang dikirim
     */
    public function send(Encounter $encounter, array $overrideReasons, User $doctor): bool
    {
        $prescription = $encounter->prescription()->with('items')->first();

        if (! $prescription || $prescription->items->isEmpty()) {
            return false;
        }

        $this->cds->recordOverrides($this->alerts($encounter), $overrideReasons, $doctor, [
            'context' => 'prescribe',
            'patient_id' => $encounter->patient_id,
            'prescription_id' => $prescription->id,
        ]);

        $prescription->forceFill([
            'status' => PrescriptionStatus::PendingVerification,
            'sent_at' => now(),
            'doctor_id' => $prescription->doctor_id ?? $doctor->id,
        ])->save();

        return true;
    }

    private function draftFor(Encounter $encounter, User $doctor): Prescription
    {
        $prescription = $encounter->prescription()->first();

        if ($prescription) {
            return $prescription;
        }

        $prescription = new Prescription(['encounter_id' => $encounter->id, 'patient_id' => $encounter->patient_id, 'doctor_id' => $doctor->id]);
        $prescription->forceFill(['status' => PrescriptionStatus::Draft])->save();

        return $prescription;
    }
}
