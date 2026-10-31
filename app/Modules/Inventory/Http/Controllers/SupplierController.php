<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $suppliers = Supplier::withCount('receipts')
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->orderByDesc('is_active')->orderBy('name')
            ->paginate(30)->withQueryString();

        return view('inventory.suppliers.index', compact('suppliers'));
    }

    public function store(Request $request): RedirectResponse
    {
        Supplier::create($this->validated($request) + ['is_active' => true]);

        return back()->with('success', 'Pemasok ditambahkan.');
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request, $supplier) + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', "Pemasok {$supplier->name} diperbarui.");
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('suppliers')->where('tenant_id', $request->user()->tenant_id)->ignore($supplier)],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
        ], ['name.unique' => 'Pemasok dengan nama ini sudah ada.'], ['name' => 'nama pemasok']);
    }
}
