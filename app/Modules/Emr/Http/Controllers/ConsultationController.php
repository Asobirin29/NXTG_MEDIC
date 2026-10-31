<?php

namespace App\Modules\Emr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cds\Models\AlertOverride;
use App\Modules\Emr\Models\ClinicalNote;
use App\Modules\Emr\Models\SoapTemplate;
use App\Modules\Emr\Services\EmrService;
use App\Modules\Emr\Services\PrescriptionService;
use App\Modules\Facility\Models\MedicalService;
use App\Modules\Patient\Enums\EncounterStatus;
use App\Modules\Patient\Models\Encounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Ruang Periksa Dokter — workspace RME tiga kolom (PRD §11.3). */
class ConsultationController extends Controller
{
    public function show(Encounter $encounter, PrescriptionService $prescriptions): View
    {
        $encounter->load([
            'patient.allergies.substance', 'poli', 'doctor', 'paymentType', 'latestVital.recorder',
            'clinicalNote.addenda.author', 'clinicalNote.doctor', 'diagnoses.icd10', 'services.service', 'prescription.items',
        ]);

        // Riwayat kunjungan sebelumnya yang rekam medisnya sudah final, terbaru dulu.
        $history = Encounter::with(['poli', 'doctor', 'latestVital', 'clinicalNote.addenda', 'diagnoses.icd10', 'services.service', 'prescription.items'])
            ->where('patient_id', $encounter->patient_id)
            ->whereKeyNot($encounter->id)
            ->whereHas('clinicalNote', fn ($q) => $q->where('status', 'locked'))
            ->latest('registered_at')
            ->limit(10)
            ->get();

        return view('emr.consultation', [
            'encounter' => $encounter,
            'patient' => $encounter->patient,
            'note' => $encounter->clinicalNote,
            'vital' => $encounter->latestVital,
            'history' => $history,
            'serviceOptions' => MedicalService::active()
                ->where(fn ($q) => $q->whereNull('poli_id')->orWhere('poli_id', $encounter->poli_id))
                ->orderBy('name')->get(),
            'templates' => SoapTemplate::orderBy('title')->get()->groupBy('section'),
            'alerts' => $prescriptions->alerts($encounter),
            'overrides' => $encounter->prescription
                ? AlertOverride::with('user')->where('prescription_id', $encounter->prescription->id)->get()->keyBy('alert_key')
                : collect(),
        ]);
    }

    public function start(Request $request, Encounter $encounter, EmrService $emr): RedirectResponse
    {
        $emr->startConsultation($encounter, $request->user());

        return redirect()->route('emr.consultation', $encounter);
    }

    /** Simpan draf SOAP (dipanggil AJAX secara berkala dan lewat tombol "Simpan draf"). */
    public function saveDraft(Request $request, Encounter $encounter, EmrService $emr): JsonResponse|RedirectResponse
    {
        $note = $emr->saveDraft($encounter, $this->soap($request));

        return $request->expectsJson()
            ? response()->json(['message' => 'Draf tersimpan', 'saved_at' => $note->updated_at->format('H:i:s')])
            : back()->with('success', 'Draf SOAP tersimpan.');
    }

    public function finish(Request $request, Encounter $encounter, EmrService $emr): RedirectResponse
    {
        $overrides = $request->validate(['overrides' => ['array'], 'overrides.*' => ['nullable', 'string', 'max:1000']])['overrides'] ?? [];

        $emr->finish($encounter, $this->soap($request), $request->user(), $overrides);

        $next = $encounter->fresh()->status === EncounterStatus::WaitingPharmacy ? 'resep dikirim ke farmasi' : 'pasien diteruskan ke kasir';

        return redirect()->route('emr.index')
            ->with('success', "Pemeriksaan {$encounter->patient->name} selesai. Rekam medis dikunci, {$next}.");
    }

    public function addendum(Request $request, Encounter $encounter, EmrService $emr): RedirectResponse
    {
        $data = $request->validate(['content' => ['required', 'string', 'max:5000']], [], ['content' => 'isi addendum']);

        $emr->addAddendum($encounter, $data['content'], $request->user());

        return back()->with('success', 'Addendum ditambahkan. Catatan asli tetap utuh.');
    }

    private function soap(Request $request): array
    {
        $rules = array_fill_keys(array_keys(ClinicalNote::SECTIONS), ['nullable', 'string', 'max:10000']);

        return $request->validate($rules);
    }
}
