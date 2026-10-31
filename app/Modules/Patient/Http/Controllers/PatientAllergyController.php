<?php

namespace App\Modules\Patient\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cds\Enums\Severity;
use App\Modules\Inventory\Models\Substance;
use App\Modules\Patient\Enums\AllergyType;
use App\Modules\Patient\Models\Patient;
use App\Modules\Patient\Models\PatientAllergy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/** Alergi pasien: obat dicatat per zat aktif agar merek lain tetap terdeteksi (PRD §6.1). */
class PatientAllergyController extends Controller
{
    public function store(Request $request, Patient $patient): RedirectResponse
    {
        $data = $request->validateWithBag('allergy', [
            'type' => ['required', new Enum(AllergyType::class)],
            'substance_id' => [
                Rule::requiredIf($request->input('type') === AllergyType::Drug->value),
                'nullable', 'integer',
                fn ($attr, $value, $fail) => $value && ! Substance::whereKey($value)->exists() ? $fail('Zat aktif tidak ditemukan.') : null,
                Rule::unique('patient_allergies')->where('patient_id', $patient->id),
            ],
            'allergen_name' => [Rule::requiredIf($request->input('type') !== AllergyType::Drug->value), 'nullable', 'string', 'max:150'],
            'severity' => ['nullable', new Enum(Severity::class)],
            'reaction' => ['nullable', 'string', 'max:255'],
        ], [
            'substance_id.required' => 'Pilih zat aktif penyebab alergi.',
            'substance_id.unique' => 'Alergi zat aktif ini sudah tercatat.',
            'allergen_name.required' => 'Sebutkan penyebab alergi.',
        ]);

        $isDrug = $data['type'] === AllergyType::Drug->value;

        PatientAllergy::create([
            'patient_id' => $patient->id,
            'type' => $data['type'],
            'substance_id' => $isDrug ? $data['substance_id'] : null,
            'allergen_name' => $isDrug ? null : $data['allergen_name'],
            'severity' => $data['severity'] ?? null,
            'reaction' => $data['reaction'] ?? null,
            'recorded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Alergi dicatat.');
    }

    public function destroy(Patient $patient, PatientAllergy $allergy): RedirectResponse
    {
        abort_unless($allergy->patient_id === $patient->id, 404);

        $allergy->delete();

        return back()->with('success', 'Catatan alergi dihapus.');
    }
}
