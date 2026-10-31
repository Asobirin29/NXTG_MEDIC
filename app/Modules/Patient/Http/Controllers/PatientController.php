<?php

namespace App\Modules\Patient\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cds\Enums\Severity;
use App\Modules\Patient\Enums\AllergyType;
use App\Modules\Patient\Models\Patient;
use App\Modules\Patient\Services\PatientService;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PatientController extends Controller
{
    public function index(Request $request): View
    {
        $term = $request->string('q')->trim()->value();

        $patients = Patient::withCount('allergies')
            ->search($term)
            ->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('patients.index', compact('patients', 'term'));
    }

    public function create(Request $request): View
    {
        // Prefill dari pencarian di pendaftaran: angka 16 digit = NIK, selain itu nama.
        $q = trim((string) $request->query('q'));
        $prefill = preg_match('/^\d{16}$/', $q) ? ['nik' => $q] : ['name' => $q];

        return view('patients.form', [
            'patient' => new Patient($prefill),
            'next' => $request->query('next'),
        ]);
    }

    public function store(Request $request, PatientService $service): RedirectResponse
    {
        $patient = $service->create($this->validated($request));

        if ($request->input('next') === 'register') {
            return redirect()->route('registration.create', ['patient' => $patient->id])
                ->with('success', "Pasien {$patient->name} terdaftar dengan No. RM {$patient->medical_record_number}.");
        }

        return redirect()->route('patients.show', $patient)
            ->with('success', "Pasien terdaftar dengan No. RM {$patient->medical_record_number}.");
    }

    public function show(Patient $patient, AuditLogger $audit): View
    {
        // Akses data pasien tercatat di audit (PRD §9).
        $audit->log('viewed', $patient);

        return view('patients.show', [
            'patient' => $patient->load('allergies.substance', 'allergies.recorder'),
            'encounters' => $patient->encounters()->with(['poli', 'doctor', 'paymentType'])->latest('registered_at')->limit(20)->get(),
            'allergyTypes' => AllergyType::options(),
            'severities' => Severity::options(),
        ]);
    }

    public function edit(Patient $patient): View
    {
        return view('patients.form', ['patient' => $patient, 'next' => null]);
    }

    public function update(Request $request, Patient $patient, PatientService $service): RedirectResponse
    {
        $service->update($patient, $this->validated($request, $patient) + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('patients.show', $patient)->with('success', 'Data pasien diperbarui.');
    }

    private function validated(Request $request, ?Patient $patient = null): array
    {
        $request->merge(['nik' => preg_replace('/\D/', '', (string) $request->input('nik')) ?: null]);

        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'birth_date' => ['required', 'date', 'before_or_equal:today', 'after:1900-01-01'],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'nik' => ['nullable', 'digits:16'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'insurance_number' => ['nullable', 'string', 'max:30'],
            'blood_type' => ['nullable', Rule::in(['A', 'B', 'AB', 'O'])],
        ], [
            'nik.digits' => 'NIK harus 16 digit angka.',
            'birth_date.before_or_equal' => 'Tanggal lahir tidak boleh di masa depan.',
        ]);
    }
}
