<?php

namespace App\Modules\Facility\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Facility\Models\PaymentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentTypeController extends Controller
{
    public function index(): View
    {
        return view('facility.payment-types.index', ['types' => PaymentType::ordered()->get()]);
    }

    public function create(): View
    {
        return view('facility.payment-types.form', [
            'type' => new PaymentType(['is_active' => true, 'patient_pays' => true, 'sort_order' => PaymentType::max('sort_order') + 1]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        PaymentType::create($this->validated($request));

        return redirect()->route('settings.payment-types.index')->with('success', 'Jenis pembayaran ditambahkan.');
    }

    public function edit(PaymentType $paymentType): View
    {
        return view('facility.payment-types.form', ['type' => $paymentType]);
    }

    public function update(Request $request, PaymentType $paymentType): RedirectResponse
    {
        $data = $this->validated($request, $paymentType);

        if (! $data['is_active'] && PaymentType::active()->whereKeyNot($paymentType->id)->doesntExist()) {
            return back()->withInput()->withErrors(['is_active' => 'Minimal satu jenis pembayaran harus tetap aktif.']);
        }

        $paymentType->update($data);

        return redirect()->route('settings.payment-types.index')->with('success', 'Jenis pembayaran diperbarui.');
    }

    private function validated(Request $request, ?PaymentType $type = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'code' => ['required', 'alpha_dash', 'max:20', Rule::unique('payment_types')->where('tenant_id', $request->user()->tenant_id)->ignore($type)],
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ]) + [
            'patient_pays' => $request->boolean('patient_pays'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
