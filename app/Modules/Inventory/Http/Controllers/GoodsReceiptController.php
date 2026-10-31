<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Drug;
use App\Modules\Inventory\Models\GoodsReceipt;
use App\Modules\Inventory\Models\Supplier;
use App\Modules\Inventory\Services\GoodsReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Penerimaan barang dari pemasok → batch baru + mutasi masuk. */
class GoodsReceiptController extends Controller
{
    public function index(Request $request): View
    {
        $receipts = GoodsReceipt::with(['supplier', 'receiver'])->withCount('items')
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('number', 'like', "%{$term}%")
                ->orWhere('invoice_number', 'like', "%{$term}%")
                ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$term}%"))))
            ->latest('received_at')->latest('id')
            ->paginate(25)->withQueryString();

        return view('inventory.receipts.index', compact('receipts'));
    }

    public function create(Request $request): View
    {
        // Saat validasi gagal, baris obat dibangun ulang dari old input beserta data kemasannya.
        $oldIds = collect($request->old('items', []))->pluck('drug_id')->filter()->map(fn ($id) => (int) $id)->all();
        $drugs = Drug::with(['packagings', 'substances'])->whereKey($oldIds)->get()->map(fn (Drug $d) => [
            'id' => $d->id, 'name' => $d->name, 'composition' => $d->compositionLabel(), 'base_unit' => $d->base_unit,
            'packagings' => $d->packagings->map(fn ($p) => ['id' => $p->id, 'unit_name' => $p->unit_name, 'quantity_in_base' => $p->quantity_in_base, 'is_default' => $p->is_default_sale])->values(),
        ])->keyBy('id');

        return view('inventory.receipts.create', [
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'oldDrugs' => $drugs,
        ]);
    }

    public function store(Request $request, GoodsReceiptService $service): RedirectResponse
    {
        $items = array_values((array) $request->input('items', []));
        $attributes = [];
        foreach ($items as $i => $_) {
            $row = $i + 1;
            $attributes += [
                "items.{$i}.drug_id" => "obat baris {$row}",
                "items.{$i}.drug_packaging_id" => "kemasan baris {$row}",
                "items.{$i}.quantity" => "jumlah baris {$row}",
                "items.{$i}.batch_number" => "nomor batch baris {$row}",
                "items.{$i}.expiry_date" => "tanggal ED baris {$row}",
                "items.{$i}.unit_price" => "harga beli baris {$row}",
            ];
        }
        $request->merge(['items' => $items]);
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'supplier_id' => ['required', 'integer', fn ($a, $v, $fail) => Supplier::active()->whereKey($v)->exists() ?: $fail('Pemasok tidak ditemukan.')],
            'invoice_number' => ['nullable', 'string', 'max:60'],
            'received_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.drug_id' => ['required', 'integer', fn ($a, $v, $fail) => Drug::whereKey($v)->exists() ?: $fail('Obat tidak ditemukan.')],
            'items.*.drug_packaging_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.batch_number' => ['required', 'string', 'max:60'],
            'items.*.expiry_date' => ['required', 'date', 'after:received_at'],
            'items.*.unit_price' => ['required', 'integer', 'min:0', 'max:1000000000'],
        ], [
            'items.required' => 'Tambahkan minimal satu obat yang diterima.',
            'items.*.expiry_date.after' => 'Tanggal ED :attribute harus setelah tanggal terima — obat kedaluwarsa tidak boleh diterima.',
        ], $attributes + ['supplier_id' => 'pemasok', 'received_at' => 'tanggal terima']);

        // Batch nomor sama untuk obat yang sama dalam satu faktur → gabungkan saja di form.
        $dupes = collect($data['items'])->groupBy(fn ($r) => $r['drug_id'].'|'.mb_strtoupper(trim($r['batch_number'])))->filter(fn ($g) => $g->count() > 1);
        if ($dupes->isNotEmpty()) {
            return back()->withInput()->withErrors(['items' => 'Ada obat dengan nomor batch yang sama lebih dari sekali. Gabungkan jumlahnya dalam satu baris.']);
        }

        $receipt = $service->receive($data, $data['items'], $request->user());

        $soon = collect($data['items'])->filter(fn ($r) => Carbon::parse($r['expiry_date'])->lte(today()->addDays(90)))->count();

        $redirect = redirect()->route('receipts.show', $receipt)->with('success', "Penerimaan {$receipt->number} tersimpan, stok bertambah.");

        return $soon ? $redirect->with('warning', "{$soon} baris ber-ED ≤ 90 hari. Batch ini otomatis dikeluarkan lebih dulu (FEFO).") : $redirect;
    }

    public function show(GoodsReceipt $receipt): View
    {
        $receipt->load(['supplier', 'receiver', 'items']);

        return view('inventory.receipts.show', compact('receipt'));
    }
}
