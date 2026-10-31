<?php

namespace App\Modules\Pos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cds\Models\AlertOverride;
use App\Modules\Facility\Models\PaymentType;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Drug;
use App\Modules\Inventory\Models\DrugPackaging;
use App\Modules\Patient\Enums\EncounterStatus;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\SaleItem;
use App\Modules\Pos\Services\CartService;
use App\Modules\Pos\Services\CheckoutService;
use App\Modules\Pos\Services\PharmacyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Layar Farmasi & Kasir (PRD §11.4): kiri antrean resep & keranjang ditahan,
 * tengah keranjang dengan batch FEFO, kanan pasien/CDS/pembayaran.
 */
class PosController extends Controller
{
    public function index(Request $request): View
    {
        return view('pos.workspace', $this->sidebar() + ['sale' => null]);
    }

    public function show(Request $request, Sale $sale, CartService $cart): View|RedirectResponse
    {
        if (! $sale->isOpen()) {
            return redirect()->route('pos.sales.show', $sale);
        }

        $sale->load(['items.drug.packagings', 'items.drug.prices', 'items.prescriptionItem', 'patient.allergies.substance', 'paymentType', 'prescription.doctor', 'encounter.poli', 'encounter.latestVital', 'creator']);

        return view('pos.workspace', $this->sidebar() + [
            'sale' => $sale,
            'quote' => $cart->quote($sale),
            'paymentTypes' => PaymentType::active()->ordered()->get(),
            'pharmacists' => User::sameTenantAs($request->user())->where('role', Role::Apoteker)->where('is_active', true)->whereNotNull('pin')->orderBy('name')->get(),
            'isPharmacist' => $request->user()->hasRole(Role::Apoteker),
            'idempotencyKey' => (string) Str::uuid(),
            // Alasan dokter saat meresepkan, sebagai bahan telaah apoteker.
            'doctorOverrides' => $sale->prescription_id
                ? AlertOverride::with('user')->where('prescription_id', $sale->prescription_id)->where('context', 'prescribe')->get()->keyBy('alert_key')
                : collect(),
        ]);
    }

    public function openEncounter(Request $request, Encounter $encounter, CartService $cart): RedirectResponse
    {
        return redirect()->route('pos.cart', $cart->openForEncounter($encounter, $request->user()));
    }

    public function openOtc(Request $request, CartService $cart): RedirectResponse
    {
        return redirect()->route('pos.cart', $cart->openOtc($request->user()));
    }

    public function addItem(Request $request, Sale $sale, CartService $cart): RedirectResponse
    {
        $data = $request->validate([
            'drug_id' => ['required', 'integer'],
            'drug_packaging_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'signa' => ['nullable', 'string', 'max:120'],
        ], [], ['drug_id' => 'obat', 'drug_packaging_id' => 'kemasan', 'quantity' => 'jumlah']);

        $drug = Drug::findOrFail($data['drug_id']);
        $packaging = DrugPackaging::where('drug_id', $drug->id)->findOrFail($data['drug_packaging_id']);
        $item = $cart->addDrug($sale, $drug, $packaging, (int) $data['quantity'], $data['signa'] ?? null);

        return redirect()->route('pos.cart', $sale)->with('success', "{$item->name} ditambahkan.");
    }

    /** Scan barcode kemasan / obat → tambah 1 kemasan itu. */
    public function scan(Request $request, Sale $sale, CartService $cart): RedirectResponse
    {
        $code = trim((string) $request->validate(['barcode' => ['required', 'string', 'max:50']])['barcode']);

        $packaging = DrugPackaging::with('drug')->where('barcode', $code)->whereHas('drug', fn ($q) => $q->active())->first();
        if (! $packaging && ($drug = Drug::active()->where('barcode', $code)->first())) {
            $packaging = $drug->packagings->firstWhere('is_default_sale', true) ?? $drug->packagings->first();
        }
        if (! $packaging) {
            return redirect()->route('pos.cart', $sale)->with('error', "Barcode {$code} tidak dikenali.");
        }

        $item = $cart->addDrug($sale, $packaging->drug, $packaging, 1, null);

        return redirect()->route('pos.cart', $sale)->with('success', "1 {$packaging->unit_name} {$item->name} ditambahkan.");
    }

    public function removeItem(Request $request, Sale $sale, SaleItem $item, CartService $cart): RedirectResponse
    {
        $cart->removeItem($sale, $item, $request->user());

        return redirect()->route('pos.cart', $sale)->with('success', "{$item->name} dikeluarkan dari keranjang.");
    }

    public function update(Request $request, Sale $sale, CartService $cart): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['nullable', 'integer', fn ($a, $v, $fail) => Patient::whereKey($v)->exists() ?: $fail('Pasien tidak ditemukan.')],
            'payment_type_id' => ['nullable', 'integer', fn ($a, $v, $fail) => PaymentType::active()->whereKey($v)->exists() ?: $fail('Jenis bayar tidak ditemukan.')],
            'external_rx_ref' => ['nullable', 'string', 'max:150'],
            'label' => ['nullable', 'string', 'max:60'],
        ]);

        $cart->updateDetails($sale, $data); // kunci yang tidak dikirim tidak diubah

        return redirect()->route('pos.cart', $sale)->with('success', 'Keranjang diperbarui.');
    }

    public function discard(Sale $sale, CartService $cart): RedirectResponse
    {
        $cart->discard($sale);

        return redirect()->route('pos.index')->with('success', 'Keranjang dibatalkan. Stok tidak berubah.');
    }

    public function verify(Request $request, Sale $sale, PharmacyService $pharmacy): RedirectResponse
    {
        $overrides = $request->validate(['overrides' => ['array'], 'overrides.*' => ['nullable', 'string', 'max:1000']])['overrides'] ?? [];

        $pharmacy->verify($sale, $overrides, $request->user());

        return redirect()->route('pos.cart', $sale)->with('success', 'Resep terverifikasi dan disiapkan. Cetak etiket, lalu lanjutkan pembayaran.');
    }

    public function checkout(Request $request, Sale $sale, CheckoutService $checkout): RedirectResponse
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'payment_method' => ['nullable', Rule::in(['cash', 'non_cash'])],
            'paid_amount' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'overrides' => ['array'],
            'overrides.*' => ['nullable', 'string', 'max:1000'],
            'approver_id' => ['nullable', 'integer'],
            'approver_pin' => ['nullable', 'string', 'max:6'],
        ]);

        $done = $checkout->checkout($sale, $data, $request->user());

        $message = "Transaksi {$done->number} lunas.";
        if ($done->change_amount > 0) {
            $message .= ' Kembalian Rp'.number_format($done->change_amount, 0, ',', '.').'.';
        }

        return redirect()->route('pos.sales.show', $done)->with('success', $message);
    }

    /** Pencarian pasien untuk keranjang OTC (cek alergi). */
    public function patients(Request $request): JsonResponse
    {
        $term = $request->string('q')->trim()->value();
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        return response()->json(Patient::where('is_active', true)->search($term)->orderBy('name')->limit(15)->get()
            ->map(fn (Patient $p) => ['id' => $p->id, 'name' => $p->name, 'detail' => $p->medical_record_number.' · '.$p->ageLabel()]));
    }

    private function sidebar(): array
    {
        $queue = Encounter::with(['patient', 'poli', 'paymentType', 'prescription'])
            ->whereIn('status', [EncounterStatus::WaitingPharmacy, EncounterStatus::ReadyBilling])
            ->orderBy('pharmacy_queued_at')->orderBy('billing_ready_at')->orderBy('id')
            ->get();

        return [
            'rxQueue' => $queue->where('status', EncounterStatus::WaitingPharmacy)->values(),
            'billingQueue' => $queue->where('status', EncounterStatus::ReadyBilling)->values(),
            'heldCarts' => Sale::with(['patient', 'encounter'])->open()->withCount('items')->latest('updated_at')->limit(20)->get(),
        ];
    }
}
