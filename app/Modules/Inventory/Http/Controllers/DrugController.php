<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Facility\Models\PaymentType;
use App\Modules\Inventory\Enums\DrugCategory;
use App\Modules\Inventory\Models\Drug;
use App\Modules\Inventory\Models\Substance;
use App\Modules\Inventory\Services\DrugService;
use App\Modules\Inventory\Services\StockLevels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

class DrugController extends Controller
{
    public function index(Request $request, StockLevels $stock): View
    {
        $drugs = Drug::with(['substances', 'packagings'])
            ->search($request->string('q')->trim()->value())
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->paginate(25)->withQueryString();

        return view('inventory.drugs.index', [
            'drugs' => $drugs,
            'levels' => $stock->available($drugs->pluck('id')->all()),
            'categories' => DrugCategory::options(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Drug(['is_active' => true, 'category' => DrugCategory::Keras, 'base_unit' => 'tablet', 'dosage_form' => 'tablet', 'min_stock' => 0]));
    }

    public function store(Request $request, DrugService $service): RedirectResponse
    {
        $drug = $this->persist(new Drug, $request, $service);

        return redirect()->route('drugs.index')->with('success', "Obat {$drug->name} ditambahkan.");
    }

    public function edit(Drug $drug): View
    {
        return $this->form($drug->load(['substances', 'packagings', 'prices']));
    }

    public function update(Request $request, Drug $drug, DrugService $service): RedirectResponse
    {
        $this->persist($drug, $request, $service);

        return redirect()->route('drugs.index')->with('success', "Obat {$drug->name} diperbarui.");
    }

    private function form(Drug $drug): View
    {
        return view('inventory.drugs.form', [
            'drug' => $drug,
            'categories' => DrugCategory::options(),
            'paymentTypes' => PaymentType::ordered()->get(),
        ]);
    }

    private function persist(Drug $drug, Request $request, DrugService $service): Drug
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'kfa_code' => ['nullable', 'string', 'max:30', Rule::unique('drugs')->where('tenant_id', $tenantId)->ignore($drug)],
            'dosage_form' => ['required', Rule::in(Drug::DOSAGE_FORMS)],
            'strength' => ['nullable', 'string', 'max:60'],
            'category' => ['required', new Enum(DrugCategory::class)],
            'base_unit' => ['required', Rule::in(Drug::BASE_UNITS)],
            'barcode' => ['nullable', 'string', 'max:50', Rule::unique('drugs')->where('tenant_id', $tenantId)->ignore($drug)],
            'min_stock' => ['required', 'integer', 'min:0'],
            'substances' => ['required', 'array', 'min:1'],
            'substances.*.substance_id' => ['required', 'integer', 'distinct', fn ($attr, $value, $fail) => Substance::whereKey($value)->exists() ?: $fail('Zat aktif tidak ditemukan.')],
            'substances.*.strength_value' => ['nullable', 'numeric', 'min:0'],
            'substances.*.strength_unit' => ['nullable', 'string', 'max:20'],
            'packagings' => ['required', 'array'],
            'packagings.*.id' => ['nullable', 'integer'],
            'packagings.*.unit_name' => ['nullable', 'string', 'max:30'],
            'packagings.*.quantity_in_base' => ['nullable', 'integer', 'min:1'],
            'packagings.*.barcode' => ['nullable', 'string', 'max:50'],
            'default_packaging' => ['nullable', 'string', 'max:40'],
            'prices' => ['array'],
            'prices.*' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ], [
            'substances.required' => 'Minimal satu zat aktif wajib diisi agar alergi & interaksi bisa terdeteksi.',
            'substances.*.substance_id.distinct' => 'Zat aktif tidak boleh dobel.',
        ]);

        // default_packaging berisi key baris form (bukan urutan), karena baris bisa dihapus di tengah.
        $packagings = collect($data['packagings'])->map(fn ($p, $key) => $p + [
            'is_default_sale' => (string) $key === (string) $request->input('default_packaging'),
        ])->values()->all();

        $validTypes = PaymentType::pluck('id')->all();
        $prices = collect($data['prices'] ?? [])->filter(fn ($p, $type) => in_array((int) $type, $validTypes, true))->all();

        return $service->save(
            $drug,
            collect($data)->only(['name', 'kfa_code', 'dosage_form', 'strength', 'category', 'base_unit', 'barcode', 'min_stock'])->all()
                + ['is_active' => $request->boolean('is_active')],
            array_values($data['substances']),
            $packagings,
            $prices,
        );
    }
}
