<?php

namespace App\Modules\Facility\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Facility\Models\MedicalService;
use App\Modules\Facility\Models\MedicalServicePrice;
use App\Modules\Facility\Models\PaymentType;
use App\Modules\Facility\Models\Poli;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Master tindakan medis poli + tarif per jenis pembayaran. */
class MedicalServiceController extends Controller
{
    public function index(Request $request): View
    {
        $services = MedicalService::with(['poli', 'prices'])
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")))
            ->when($request->query('poli_id'), fn ($q, $poli) => $q->where('poli_id', $poli))
            ->orderByDesc('is_active')->orderBy('name')
            ->paginate(25)->withQueryString();

        return view('facility.services.index', [
            'services' => $services,
            'paymentTypes' => PaymentType::active()->ordered()->get(),
            'polis' => Poli::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new MedicalService(['is_active' => true]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->persist(new MedicalService, $request);

        return redirect()->route('settings.services.index')->with('success', 'Tindakan ditambahkan.');
    }

    public function edit(MedicalService $service): View
    {
        return $this->form($service->load('prices'));
    }

    public function update(Request $request, MedicalService $service): RedirectResponse
    {
        $this->persist($service, $request);

        return redirect()->route('settings.services.index')->with('success', 'Tindakan diperbarui.');
    }

    private function form(MedicalService $service): View
    {
        return view('facility.services.form', [
            'service' => $service,
            'paymentTypes' => PaymentType::ordered()->get(),
            'polis' => Poli::active()->orderBy('name')->get(),
        ]);
    }

    private function persist(MedicalService $service, Request $request): void
    {
        $tenantId = $request->user()->tenant_id;
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('medical_services')->where('tenant_id', $tenantId)->ignore($service)],
            'name' => ['required', 'string', 'max:150'],
            'poli_id' => ['nullable', Rule::exists('polis', 'id')->where('tenant_id', $tenantId)],
            'prices' => ['array'],
            'prices.*' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ]);

        $validTypes = PaymentType::pluck('id')->all();

        DB::transaction(function () use ($service, $data, $request, $validTypes) {
            $service->fill([
                'code' => $data['code'],
                'name' => $data['name'],
                'poli_id' => $data['poli_id'] ?? null,
                'is_active' => $request->boolean('is_active'),
            ])->save();

            foreach ($data['prices'] ?? [] as $typeId => $price) {
                if (! in_array((int) $typeId, $validTypes, true)) {
                    continue;
                }

                $current = MedicalServicePrice::where('medical_service_id', $service->id)->where('payment_type_id', $typeId)->first();

                if ($price === null || $price === '') {
                    $current?->delete();
                } elseif ($current) {
                    $current->update(['price' => (int) $price]);
                } else {
                    MedicalServicePrice::create(['medical_service_id' => $service->id, 'payment_type_id' => (int) $typeId, 'price' => (int) $price]);
                }
            }
        });
    }
}
