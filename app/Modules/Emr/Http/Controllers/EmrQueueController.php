<?php

namespace App\Modules\Emr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Facility\Models\Poli;
use App\Modules\Identity\Enums\Role;
use App\Modules\Patient\Enums\EncounterStatus;
use App\Modules\Patient\Models\Encounter;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Antrean poli untuk perawat & dokter (halaman awal mereka, PRD §11.1). */
class EmrQueueController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $tab = $request->query('tab', 'aktif');
        $mine = $user->hasRole(Role::Dokter) && $request->boolean('saya', true);

        $encounters = Encounter::with(['patient.allergies.substance', 'poli', 'doctor', 'paymentType', 'latestVital'])
            ->onDate(today())
            ->when($request->query('poli_id'), fn ($q, $poli) => $q->where('poli_id', $poli))
            ->when($mine, fn ($q) => $q->where(fn ($q) => $q->whereNull('doctor_id')->orWhere('doctor_id', $user->id)))
            ->when($tab === 'aktif',
                fn ($q) => $q->whereIn('status', [EncounterStatus::Queued, EncounterStatus::NurseAssessed, EncounterStatus::InConsultation]),
                fn ($q) => $q->whereIn('status', [EncounterStatus::WaitingPharmacy, EncounterStatus::ReadyBilling, EncounterStatus::Completed]))
            ->orderByRaw("FIELD(status, 'IN_CONSULTATION', 'NURSE_ASSESSED', 'QUEUED')")
            ->orderBy('queue_number')
            ->get();

        return view('emr.queue', [
            'encounters' => $encounters,
            'polis' => Poli::active()->orderBy('name')->get(),
            'tab' => $tab,
            'mine' => $mine,
            'isDoctor' => $user->hasRole(Role::Dokter),
        ]);
    }
}
