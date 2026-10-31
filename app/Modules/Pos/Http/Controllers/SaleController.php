<?php

namespace App\Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Services\CartService;
use App\Modules\Pos\Services\SaleReversalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Riwayat transaksi, struk, etiket, void, dan retur. */
class SaleController extends Controller
{
    public function index(Request $request): View
    {
        $sales = Sale::with(['patient', 'paymentType', 'cashier'])
            ->whereIn('status', [Sale::COMPLETED, Sale::VOIDED])
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('number', 'like', "%{$term}%")
                ->orWhere('label', 'like', "%{$term}%")
                ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', "%{$term}%")->orWhere('medical_record_number', 'like', "{$term}%"))))
            ->when($request->query('date'), fn ($q, $d) => $q->whereDate('completed_at', $d))
            ->latest('completed_at')
            ->paginate(25)->withQueryString();

        $today = Sale::where('status', Sale::COMPLETED)->whereDate('completed_at', today());

        return view('pos.sales.index', [
            'sales' => $sales,
            'todayCount' => (clone $today)->count(),
            'todayCollected' => (int) (clone $today)->sum('amount_due'),
            'todayValue' => (int) (clone $today)->sum('total_value'),
        ]);
    }

    public function show(Request $request, Sale $sale, SaleReversalService $reversal): View|RedirectResponse
    {
        if ($sale->isOpen()) {
            return redirect()->route('pos.cart', $sale);
        }

        $sale->load(['items.batches.batch', 'items.drug', 'patient', 'paymentType', 'cashier', 'voider', 'encounter.poli', 'prescription.doctor', 'returns.items.saleItem', 'returns.approver', 'returns.user']);

        return view('pos.sales.show', [
            'sale' => $sale,
            'restricted' => $reversal->containsRestricted($sale),
            'pharmacists' => User::sameTenantAs($request->user())->where('role', Role::Apoteker)->where('is_active', true)->whereNotNull('pin')->orderBy('name')->get(),
            'isPharmacist' => $request->user()->hasRole(Role::Apoteker),
        ]);
    }

    public function void(Request $request, Sale $sale, SaleReversalService $reversal): RedirectResponse
    {
        $data = $request->validate([
            'void_reason' => ['required', 'string', 'min:5', 'max:500'],
            'approver_id' => ['nullable', 'integer'],
            'approver_pin' => ['nullable', 'string', 'max:6'],
        ], [], ['void_reason' => 'alasan void']);

        $reversal->void($sale, $data['void_reason'], $request->user(), $data['approver_id'] ?? null, $data['approver_pin'] ?? null);

        return redirect()->route('pos.sales.show', $sale)->with('success', "Transaksi {$sale->number} di-void. Stok dikembalikan ke batch asal".($sale->encounter_id ? ' dan tagihan kunjungan dibuka ulang.' : '.'));
    }

    public function storeReturn(Request $request, Sale $sale, SaleReversalService $reversal): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'lines' => ['required', 'array'],
            'lines.*.quantity' => ['nullable', 'integer', 'min:0'],
            'lines.*.condition' => ['required', Rule::in(['sellable', 'quarantine'])],
            'approver_id' => ['required', 'integer'],
            'approver_pin' => ['required', 'string', 'max:6'],
        ], [], ['reason' => 'alasan retur', 'approver_id' => 'apoteker', 'approver_pin' => 'PIN apoteker']);

        $return = $reversal->returnItems($sale, $data['lines'], $data['reason'], $request->user(), (int) $data['approver_id'], $data['approver_pin']);

        return redirect()->route('pos.sales.show', $sale)->with('success', "Retur {$return->number} tercatat. Uang kembali ke pasien Rp".number_format($return->refund_amount, 0, ',', '.').'.');
    }

    public function receipt(Sale $sale): View
    {
        abort_unless($sale->isCompleted() || $sale->status === Sale::VOIDED, 404);
        $sale->load(['items', 'patient', 'paymentType', 'cashier', 'tenant']);

        return view('pos.print.receipt', ['sale' => $sale]);
    }

    /** Etiket aturan pakai per obat. Sebelum bayar memakai rencana FEFO; sesudahnya batch sebenarnya. */
    public function labels(Sale $sale, CartService $cart): View
    {
        $sale->load(['items.batches.batch', 'patient', 'tenant', 'prescription.doctor']);
        $quote = $sale->isOpen() ? $cart->quote($sale) : null;

        $labels = $sale->items->where('kind', 'drug')->map(function ($item) use ($quote) {
            $expiries = $quote
                ? collect($quote['lines'][$item->id]['allocation'])->pluck('expiry_date')
                : $item->batches->map(fn ($b) => $b->batch->expiry_date);

            return ['item' => $item, 'expiry' => $expiries->filter()->min(), 'batches' => $quote
                ? collect($quote['lines'][$item->id]['allocation'])->pluck('batch_number')->implode(', ')
                : $item->batches->map(fn ($b) => $b->batch->batch_number)->implode(', ')];
        });

        return view('pos.print.labels', ['sale' => $sale, 'labels' => $labels]);
    }
}
