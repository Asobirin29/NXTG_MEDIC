<?php

namespace App\Modules\Emr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Emr\Models\PrescriptionItem;
use App\Modules\Emr\Services\PrescriptionService;
use App\Modules\Inventory\Models\Drug;
use App\Modules\Inventory\Models\DrugPackaging;
use App\Modules\Patient\Models\Encounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** e-Resep di ruang periksa (AJAX). Setiap respons membawa daftar item + hasil cek CDS terbaru. */
class PrescriptionController extends Controller
{
    public function __construct(private PrescriptionService $prescriptions) {}

    public function store(Request $request, Encounter $encounter): JsonResponse
    {
        $data = $request->validate([
            'drug_id' => ['required', 'integer'],
            'drug_packaging_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'signa' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['signa.required' => 'Aturan pakai (signa) wajib diisi.'], [
            'drug_id' => 'obat', 'drug_packaging_id' => 'kemasan', 'quantity' => 'jumlah', 'signa' => 'aturan pakai',
        ]);

        $drug = Drug::findOrFail($data['drug_id']);
        $packaging = DrugPackaging::where('drug_id', $drug->id)->findOrFail($data['drug_packaging_id']);

        $this->prescriptions->addItem($encounter, $drug, $packaging, (int) $data['quantity'], $data['signa'], $data['note'] ?? null, $request->user());

        return $this->state($encounter);
    }

    public function destroy(Encounter $encounter, PrescriptionItem $item): JsonResponse
    {
        $this->prescriptions->removeItem($encounter, $item);

        return $this->state($encounter);
    }

    public function copyLast(Request $request, Encounter $encounter): JsonResponse
    {
        $result = $this->prescriptions->copyLast($encounter, $request->user());

        $message = "{$result['copied']} obat disalin dari resep {$result['date']?->format('d/m/Y')}.";
        if ($result['skipped']) {
            $message .= ' Dilewati: '.implode(', ', $result['skipped']).'.';
        }

        return $this->state($encounter, $message);
    }

    private function state(Encounter $encounter, ?string $message = null): JsonResponse
    {
        $encounter->unsetRelation('prescription')->load('prescription.items', 'patient');
        $alerts = $this->prescriptions->alerts($encounter);

        return response()->json([
            'message' => $message,
            'items_html' => view('emr.partials.prescription-items', ['encounter' => $encounter, 'editable' => true])->render(),
            'alerts_html' => view('emr.partials.cds-alerts', ['alerts' => $alerts, 'editable' => true])->render(),
            'alerts' => array_map(fn ($a) => $a->toArray(), $alerts),
            'item_ids' => $encounter->prescription?->items->pluck('id', 'drug_id') ?? [],
            'count' => $encounter->prescription?->items->count() ?? 0,
        ]);
    }
}
