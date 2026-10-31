<?php

namespace App\Modules\Patient\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Facility\Models\PaymentType;
use App\Modules\Facility\Models\Poli;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Patient\Enums\EncounterStatus;
use App\Modules\Patient\Exceptions\InvalidEncounterTransition;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use App\Modules\Patient\Services\EncounterStateMachine;
use App\Modules\Patient\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Loket pendaftaran & papan antrean hari ini. */
class RegistrationController extends Controller
{
    public function index(Request $request): View
    {
        $encounters = Encounter::with(['patient.allergies', 'poli', 'doctor', 'paymentType'])
            ->onDate(today())
            ->when($request->query('poli_id'), fn ($q, $poli) => $q->where('poli_id', $poli))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByRaw("FIELD(status, 'QUEUED', 'NURSE_ASSESSED', 'IN_CONSULTATION', 'WAITING_PHARMACY', 'READY_BILLING', 'COMPLETED', 'CANCELLED')")
            ->orderBy('registered_at')
            ->get();

        $counts = Encounter::onDate(today())
            ->when($request->query('poli_id'), fn ($q, $poli) => $q->where('poli_id', $poli))
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('registration.index', [
            'encounters' => $encounters,
            'counts' => $counts,
            'polis' => Poli::active()->orderBy('name')->get(),
            'statuses' => EncounterStatus::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        $patient = $request->query('patient') ? Patient::with('allergies.substance')->findOrFail($request->query('patient')) : null;
        $term = $request->string('q')->trim()->value();

        return view('registration.create', [
            'patient' => $patient,
            'term' => $term,
            'results' => ! $patient && $term !== '' ? Patient::search($term)->where('is_active', true)->limit(15)->get() : collect(),
            'polis' => Poli::active()->orderBy('name')->get(),
            'paymentTypes' => PaymentType::active()->ordered()->get(),
            'doctors' => User::sameTenantAs($request->user())->where('role', Role::Dokter)->where('is_active', true)->orderBy('name')->get(),
            'todayVisits' => $patient ? Encounter::with('poli')->where('patient_id', $patient->id)->onDate(today())->get() : collect(),
        ]);
    }

    public function store(Request $request, RegistrationService $service): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'patient_id' => ['required', 'integer'],
            'poli_id' => ['required', Rule::exists('polis', 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'payment_type_id' => ['required', Rule::exists('payment_types', 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'doctor_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)->where('role', Role::Dokter->value)->where('is_active', true)],
        ]);

        $encounter = $service->register(
            Patient::findOrFail($data['patient_id']),
            Poli::findOrFail($data['poli_id']),
            PaymentType::findOrFail($data['payment_type_id']),
            isset($data['doctor_id']) ? User::find($data['doctor_id']) : null,
            $request->user(),
        );

        return redirect()->route('registration.index')
            ->with('success', "{$encounter->patient->name} masuk antrean {$encounter->poli->name} — nomor {$encounter->queueLabel()}.");
    }

    public function cancel(Request $request, Encounter $encounter, EncounterStateMachine $machine): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], ['reason.required' => 'Alasan pembatalan wajib diisi.']);

        try {
            $machine->transition($encounter, EncounterStatus::Cancelled, $data['reason']);
        } catch (InvalidEncounterTransition $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Kunjungan {$encounter->queueLabel()} dibatalkan.");
    }
}
