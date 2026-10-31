<?php

namespace App\Modules\Emr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cds\Enums\Severity;
use App\Modules\Emr\Models\VitalSign;
use App\Modules\Emr\Services\EmrService;
use App\Modules\Identity\Enums\Role;
use App\Modules\Patient\Enums\AllergyType;
use App\Modules\Patient\Models\Encounter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Asesmen awal perawat: tanda vital, antropometri, keluhan awal, alergi (PRD §8). */
class NurseAssessmentController extends Controller
{
    public function show(Encounter $encounter): View
    {
        $encounter->load(['patient.allergies.substance', 'poli', 'paymentType', 'vitalSigns.recorder']);

        return view('emr.assessment', [
            'encounter' => $encounter,
            'patient' => $encounter->patient,
            'allergyTypes' => AllergyType::options(),
            'severities' => Severity::options(),
        ]);
    }

    public function store(Request $request, Encounter $encounter, EmrService $emr): RedirectResponse
    {
        $rules = ['chief_complaint' => ['nullable', 'string', 'max:2000']];
        foreach (VitalSign::RANGES as $field => [$min, $max]) {
            $rules[$field] = ['nullable', 'numeric', "between:{$min},{$max}"];
        }

        $data = $request->validate(array_merge($rules, [
            'diastolic' => ['nullable', 'required_with:systolic', 'numeric', 'between:20,200', 'lt:systolic'],
            'systolic' => ['nullable', 'required_with:diastolic', 'numeric', 'between:40,300'],
        ]), [
            'diastolic.lt' => 'Diastolik harus lebih kecil dari sistolik.',
        ], [
            'systolic' => 'sistolik', 'diastolic' => 'diastolik', 'heart_rate' => 'nadi', 'respiratory_rate' => 'laju napas',
            'temperature' => 'suhu', 'spo2' => 'SpO2', 'weight' => 'berat badan', 'height' => 'tinggi badan', 'chief_complaint' => 'keluhan',
        ]);

        if (collect(VitalSign::MEASUREMENTS)->every(fn ($f) => blank($data[$f] ?? null))) {
            throw ValidationException::withMessages(['systolic' => 'Isi minimal satu pengukuran tanda vital.']);
        }

        // Anak: berat badan wajib karena menjadi acuan verifikasi dosis (PRD §6.2).
        if ($encounter->patient->isChild() && blank($data['weight'] ?? null)) {
            throw ValidationException::withMessages(['weight' => 'Berat badan wajib diisi untuk pasien anak (acuan dosis).']);
        }

        $emr->recordVitals($encounter, $data, $request->user());

        $user = $request->user();
        if ($user->hasRole(Role::Dokter)) {
            return redirect()->route('emr.consultation', $encounter)->with('success', 'Tanda vital disimpan.');
        }

        return redirect()->route('emr.index')->with('success', "Asesmen {$encounter->patient->name} disimpan. Pasien siap diperiksa dokter.");
    }
}
