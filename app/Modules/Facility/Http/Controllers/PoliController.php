<?php

namespace App\Modules\Facility\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Facility\Models\Poli;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Master poli. Tidak dihapus, cukup dinonaktifkan agar riwayat kunjungan tetap utuh. */
class PoliController extends Controller
{
    public function index(): View
    {
        return view('facility.polis.index', ['polis' => Poli::orderByDesc('is_active')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('facility.polis.form', ['poli' => new Poli(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Poli::create($this->validated($request));

        return redirect()->route('settings.polis.index')->with('success', 'Poli ditambahkan.');
    }

    public function edit(Poli $poli): View
    {
        return view('facility.polis.form', compact('poli'));
    }

    public function update(Request $request, Poli $poli): RedirectResponse
    {
        $poli->update($this->validated($request, $poli));

        return redirect()->route('settings.polis.index')->with('success', 'Poli diperbarui.');
    }

    private function validated(Request $request, ?Poli $poli = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'code' => ['required', 'alpha_dash', 'max:20', Rule::unique('polis')->where('tenant_id', $request->user()->tenant_id)->ignore($poli)],
            'name' => ['required', 'string', 'max:100'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
