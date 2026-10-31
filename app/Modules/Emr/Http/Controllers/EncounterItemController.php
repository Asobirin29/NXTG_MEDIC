<?php

namespace App\Modules\Emr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Emr\Models\EncounterDiagnosis;
use App\Modules\Emr\Models\EncounterService;
use App\Modules\Emr\Models\Icd10;
use App\Modules\Emr\Services\EmrService;
use App\Modules\Facility\Models\MedicalService;
use App\Modules\Patient\Models\Encounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Diagnosis & tindakan di ruang periksa, lewat AJAX agar teks SOAP yang
 * sedang diketik tidak hilang. Setiap respons mengembalikan HTML daftar terbaru.
 */
class EncounterItemController extends Controller
{
    public function storeDiagnosis(Request $request, Encounter $encounter, EmrService $emr): JsonResponse
    {
        $data = $request->validate([
            'icd10_id' => ['required', Rule::exists('icd10_masters', 'id')->where('is_active', true)],
            'type' => ['required', Rule::in(['primary', 'secondary'])],
        ], [], ['icd10_id' => 'diagnosis ICD-10']);

        $emr->addDiagnosis($encounter, Icd10::findOrFail($data['icd10_id']), $data['type']);

        return $this->diagnoses($encounter);
    }

    public function destroyDiagnosis(Encounter $encounter, EncounterDiagnosis $diagnosis, EmrService $emr): JsonResponse
    {
        $emr->removeDiagnosis($encounter, $diagnosis);

        return $this->diagnoses($encounter);
    }

    public function storeService(Request $request, Encounter $encounter, EmrService $emr): JsonResponse
    {
        $data = $request->validate([
            'medical_service_id' => ['required', Rule::exists('medical_services', 'id')->where('tenant_id', $request->user()->tenant_id)->where('is_active', true)],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ], [], ['medical_service_id' => 'tindakan', 'quantity' => 'jumlah']);

        $emr->addService($encounter, MedicalService::findOrFail($data['medical_service_id']), (int) $data['quantity'], $request->user());

        return $this->services($encounter);
    }

    public function destroyService(Encounter $encounter, EncounterService $service, EmrService $emr): JsonResponse
    {
        $emr->removeService($encounter, $service);

        return $this->services($encounter);
    }

    private function diagnoses(Encounter $encounter): JsonResponse
    {
        $encounter->load('diagnoses.icd10');

        return response()->json(['html' => view('emr.partials.diagnoses', ['encounter' => $encounter, 'editable' => true])->render()]);
    }

    private function services(Encounter $encounter): JsonResponse
    {
        $encounter->load('services.service');

        return response()->json(['html' => view('emr.partials.services', ['encounter' => $encounter, 'editable' => true])->render()]);
    }
}
