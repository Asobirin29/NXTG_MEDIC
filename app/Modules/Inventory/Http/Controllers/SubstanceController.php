<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Substance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Zat aktif: referensi bersama + tambahan faskes sendiri. */
class SubstanceController extends Controller
{
    public function index(Request $request): View
    {
        $substances = Substance::withCount('drugs')
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->orderBy('name')
            ->paginate(30)->withQueryString();

        return view('inventory.substances.index', compact('substances'));
    }

    public function store(Request $request): RedirectResponse
    {
        Substance::create($this->validated($request));

        return back()->with('success', 'Zat aktif ditambahkan.');
    }

    public function update(Request $request, Substance $substance): RedirectResponse
    {
        abort_if($substance->isShared(), 403, 'Zat aktif referensi bersama tidak bisa diubah faskes.');

        $substance->update($this->validated($request, $substance));

        return back()->with('success', 'Zat aktif diperbarui.');
    }

    /** Pencarian untuk pemilih zat aktif (Tom Select) — dipakai juga form alergi pasien. */
    public function search(Request $request): JsonResponse
    {
        $term = $request->string('q')->trim()->value();

        return response()->json(
            Substance::query()
                ->when($term, fn ($q) => $q->where('name', 'like', "%{$term}%"))
                ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', ["{$term}%"])
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name', 'atc_code']),
        );
    }

    private function validated(Request $request, ?Substance $substance = null): array
    {
        $tenantId = $request->user()->tenant_id;

        return $request->validate([
            'name' => ['required', 'string', 'max:150',
                Rule::unique('substances')->where(fn ($q) => $q->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId)))->ignore($substance)],
            'atc_code' => ['nullable', 'string', 'max:10'],
        ], ['name.unique' => 'Zat aktif dengan nama ini sudah ada.']);
    }
}
