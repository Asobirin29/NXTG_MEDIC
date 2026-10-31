<?php

namespace Tests\Feature\Pos;

use App\Modules\Cds\Models\AlertOverride;
use App\Modules\Emr\Enums\PrescriptionStatus;
use App\Modules\Emr\Models\Icd10;
use App\Modules\Emr\Models\Prescription;
use App\Modules\Facility\Models\MedicalService;
use App\Modules\Facility\Models\MedicalServicePrice;
use App\Modules\Facility\Models\PaymentType;
use App\Modules\Facility\Models\Poli;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Drug;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Substance;
use App\Modules\Inventory\Models\Supplier;
use App\Modules\Inventory\Services\DrugService;
use App\Modules\Inventory\Services\GoodsReceiptService;
use App\Modules\Inventory\Services\StockLevels;
use App\Modules\Patient\Enums\EncounterStatus;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use App\Modules\Patient\Models\PatientAllergy;
use App\Modules\Patient\Services\RegistrationService;
use App\Modules\Pos\Models\Sale;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PosTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    private User $apoteker;

    private User $dokter;

    private PaymentType $umum;

    private PaymentType $bpjs;

    private Poli $poli;

    private MedicalService $konsul;

    private Patient $patient;

    /** @var array<string, Drug> */
    private array $drugs = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->kasir = $this->makeUser(Role::Kasir);
        $tenant = $this->kasir->tenant;
        $this->apoteker = $this->makeUser(Role::Apoteker, $tenant, ['pin' => '4321']);
        $this->dokter = $this->makeUser(Role::Dokter, $tenant);

        $this->tenant(function () {
            $this->umum = PaymentType::create(['code' => 'UMUM', 'name' => 'Umum', 'patient_pays' => true, 'sort_order' => 1]);
            $this->bpjs = PaymentType::create(['code' => 'BPJS', 'name' => 'BPJS', 'patient_pays' => false, 'sort_order' => 2]);
            $this->poli = Poli::create(['code' => 'UMUM', 'name' => 'Umum']);
            $this->konsul = MedicalService::create(['code' => 'KONS', 'name' => 'Konsultasi']);
            foreach ([$this->umum, $this->bpjs] as $pt) {
                MedicalServicePrice::create(['medical_service_id' => $this->konsul->id, 'payment_type_id' => $pt->id, 'price' => 50000]);
            }

            $s = collect(['Parasetamol', 'Amoksisilin', 'Diazepam'])->mapWithKeys(fn ($n) => [$n => Substance::create(['name' => $n])]);
            $make = fn ($name, $sub, $cat, $price) => app(DrugService::class)->save(new Drug,
                ['name' => $name, 'dosage_form' => 'tablet', 'category' => $cat, 'base_unit' => 'tablet', 'min_stock' => 0, 'is_active' => true],
                [['substance_id' => $s[$sub]->id]],
                [['unit_name' => 'Tablet', 'quantity_in_base' => 1], ['unit_name' => 'Strip', 'quantity_in_base' => 10, 'is_default_sale' => true, 'barcode' => strtoupper(Str::random(8))]],
                [$this->umum->id => $price, $this->bpjs->id => $price]);
            $this->drugs = [
                'pct' => $make('Paracetamol 500', 'Parasetamol', 'bebas', 300),
                'amox' => $make('Amoxicillin 500', 'Amoksisilin', 'keras', 700),
                'dzp' => $make('Diazepam 5', 'Diazepam', 'psikotropika', 600),
            ];

            // Dua batch parasetamol: ED dekat (15) dan ED jauh (100) → FEFO lintas batch.
            $supplier = Supplier::create(['name' => 'PBF', 'is_active' => true]);
            $row = fn ($key, $qty, $batch, $days, $price) => ['drug_id' => $this->drugs[$key]->id, 'drug_packaging_id' => $this->drugs[$key]->packagings->firstWhere('unit_name', 'Tablet')->id,
                'quantity' => $qty, 'batch_number' => $batch, 'expiry_date' => today()->addDays($days)->toDateString(), 'unit_price' => $price];
            app(GoodsReceiptService::class)->receive(['supplier_id' => $supplier->id, 'received_at' => today()->toDateString()], [
                $row('pct', 15, 'P-SOON', 60, 100), $row('pct', 100, 'P-LATE', 400, 120),
                $row('amox', 50, 'A-1', 300, 400), $row('dzp', 20, 'D-1', 300, 350),
            ], $this->apoteker);

            $this->patient = Patient::factory()->for($this->kasir->tenant)->create();
        });
    }

    private function tenant(callable $fn): mixed
    {
        return app(TenantContext::class)->runAs($this->kasir->tenant_id, $fn);
    }

    /** Kunjungan sampai dokter selesai: resep (opsional) + 1 tindakan konsultasi. */
    private function encounter(array $rx = ['pct' => 2], ?PaymentType $pt = null): Encounter
    {
        $encounter = $this->tenant(fn () => app(RegistrationService::class)->register($this->patient, $this->poli, $pt ?? $this->umum, null, $this->kasir));
        $icd = Icd10::firstOrCreate(['code' => 'J06.9'], ['name_id' => 'ISPA']);

        $this->actingAs($this->dokter)->post(route('emr.start', $encounter));
        $this->actingAs($this->dokter)->postJson(route('emr.diagnoses.store', $encounter), ['icd10_id' => $icd->id, 'type' => 'primary']);
        $this->actingAs($this->dokter)->postJson(route('emr.services.store', $encounter), ['medical_service_id' => $this->konsul->id, 'quantity' => 1])->assertOk();
        foreach ($rx as $key => $strips) {
            $this->actingAs($this->dokter)->postJson(route('emr.prescription.store', $encounter), [
                'drug_id' => $this->drugs[$key]->id, 'drug_packaging_id' => $this->drugs[$key]->packagings->firstWhere('unit_name', 'Strip')->id,
                'quantity' => $strips, 'signa' => '3 x 1 tablet',
            ])->assertOk();
        }
        $this->actingAs($this->dokter)->put(route('emr.finish', $encounter), ['subjective' => 'Demam'])->assertSessionHasNoErrors();

        return $encounter->fresh();
    }

    private function open(Encounter $encounter, ?User $as = null): Sale
    {
        $this->actingAs($as ?? $this->kasir)->post(route('pos.open-encounter', $encounter))->assertRedirect()->assertSessionMissing('error');

        return $this->tenant(fn () => Sale::open()->where('encounter_id', $encounter->id)->firstOrFail());
    }

    private function pay(Sale $sale, array $data = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->kasir)->post(route('pos.cart.checkout', $sale), $data + [
            'idempotency_key' => (string) Str::uuid(), 'payment_method' => 'cash', 'paid_amount' => 1000000,
        ]);
    }

    private function available(string $key): int
    {
        return $this->tenant(fn () => app(StockLevels::class)->available([$this->drugs[$key]->id])[$this->drugs[$key]->id]);
    }

    private function qty(string $batch): int
    {
        return $this->tenant(fn () => Batch::where('batch_number', $batch)->where('status', 'available')->value('qty_on_hand'));
    }

    public function test_prescription_flow_verify_then_pay_with_fefo_across_batches(): void
    {
        $encounter = $this->encounter(['pct' => 2]); // 20 tablet
        $this->assertSame(EncounterStatus::WaitingPharmacy, $encounter->status);

        $sale = $this->open($encounter);
        $this->assertSame(['drug', 'service'], $sale->items->pluck('kind')->all());

        // Kasir tidak bisa memverifikasi, dan belum bisa membayar sebelum verifikasi.
        $this->actingAs($this->kasir)->post(route('pos.cart.verify', $sale))->assertForbidden();
        $this->pay($sale)->assertSessionHas('error', fn ($m) => str_contains($m, 'belum diverifikasi'));

        $this->actingAs($this->apoteker)->post(route('pos.cart.verify', $sale))->assertSessionHasNoErrors();
        $this->assertSame(EncounterStatus::ReadyBilling, $encounter->fresh()->status);
        $this->assertSame(PrescriptionStatus::Prepared, $this->tenant(fn () => Prescription::first()->status));

        $this->pay($sale, ['paid_amount' => 70000])->assertSessionHasNoErrors()->assertRedirect(route('pos.sales.show', $sale));

        $sale = $sale->fresh(['items.batches.batch']);
        $this->assertSame(Sale::COMPLETED, $sale->status);
        $this->assertSame('TRX-'.now()->format('Ymd').'-0001', $sale->number);
        $this->assertSame(20 * 300 + 50000, $sale->total_value);
        $this->assertSame($sale->total_value, $sale->amount_due);
        $this->assertSame(70000 - 56000, $sale->change_amount);

        // FEFO: 15 dari batch ED dekat, 5 dari batch berikutnya; HPP mengikuti harga beli batch.
        $drugItem = $sale->items->firstWhere('kind', 'drug');
        $this->assertSame(['P-SOON' => 15, 'P-LATE' => 5], $drugItem->batches->mapWithKeys(fn ($b) => [$b->batch->batch_number => $b->quantity])->all());
        $this->assertSame('2100.00', $drugItem->cost_total); // 15×100 + 5×120
        $this->assertSame([0, 95], [$this->qty('P-SOON'), $this->qty('P-LATE')]);

        $this->assertSame(EncounterStatus::Completed, $encounter->fresh()->status);
        $this->assertSame(PrescriptionStatus::Dispensed, $this->tenant(fn () => Prescription::first()->status));
        $this->assertSame(2, $this->tenant(fn () => StockMovement::where('type', 'dispense')->where('reference_type', Sale::class)->where('reference_id', $sale->id)->count()));
    }

    public function test_double_submit_with_same_key_cuts_stock_once(): void
    {
        $sale = $this->tenant(fn () => Sale::create(['type' => 'otc', 'payment_type_id' => $this->umum->id, 'created_by' => $this->kasir->id]));
        $this->actingAs($this->kasir)->post(route('pos.cart.items.store', $sale), ['drug_id' => $this->drugs['pct']->id, 'drug_packaging_id' => $this->drugs['pct']->packagings->firstWhere('unit_name', 'Strip')->id, 'quantity' => 1]);

        $key = (string) Str::uuid();
        $this->pay($sale, ['idempotency_key' => $key])->assertSessionHasNoErrors();
        $this->pay($sale, ['idempotency_key' => $key])->assertRedirect(route('pos.sales.show', $sale))->assertSessionMissing('error');
        $this->pay($sale)->assertSessionHas('error'); // kunci lain → keranjang sudah lunas

        $this->assertSame(105, $this->available('pct'));
        $this->assertSame(1, $this->tenant(fn () => Sale::where('status', 'completed')->count()));
    }

    public function test_bpjs_patient_pays_zero_but_value_and_cost_are_recorded(): void
    {
        $sale = $this->open($this->encounter(['pct' => 1], $this->bpjs));
        $this->actingAs($this->apoteker)->post(route('pos.cart.verify', $sale));

        $this->actingAs($this->kasir)->post(route('pos.cart.checkout', $sale), ['idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();

        $sale->refresh();
        $this->assertSame([10 * 300 + 50000, 0, null], [$sale->total_value, $sale->amount_due, $sale->payment_method]);
        $this->assertSame('1000.00', $sale->cost_total);
    }

    public function test_encounter_without_prescription_bills_services_only(): void
    {
        $encounter = $this->encounter([]);
        $this->assertSame(EncounterStatus::ReadyBilling, $encounter->status);

        $sale = $this->open($encounter);
        $this->pay($sale, ['payment_method' => 'non_cash'])->assertSessionHasNoErrors();

        $this->assertSame([50000, 50000, 0], [$sale->fresh()->amount_due, $sale->fresh()->paid_amount, $sale->fresh()->change_amount]);
        $this->assertSame(EncounterStatus::Completed, $encounter->fresh()->status);
    }

    public function test_otc_rules_rx_reference_payment_and_stock(): void
    {
        $this->actingAs($this->kasir)->post(route('pos.open-otc'))->assertRedirect();
        $sale = $this->tenant(fn () => Sale::open()->firstOrFail());
        $add = fn ($key, $qty, $unit = 'Strip') => $this->actingAs($this->kasir)->post(route('pos.cart.items.store', $sale), [
            'drug_id' => $this->drugs[$key]->id, 'drug_packaging_id' => $this->drugs[$key]->packagings->firstWhere('unit_name', $unit)->id, 'quantity' => $qty,
        ]);

        $add('amox', 1)->assertSessionHasNoErrors();
        $add('amox', 1); // digabung jadi 2 strip
        $this->assertSame([2, 20], [$sale->items()->first()->quantity, $sale->items()->first()->quantity_base]);

        // Obat keras di luar resep poli → wajib rujukan resep luar.
        $this->pay($sale)->assertSessionHas('error', fn ($m) => str_contains($m, 'rujukan resep luar'));
        $this->actingAs($this->kasir)->put(route('pos.cart.update', $sale), ['external_rx_ref' => 'dr. Rina, R/123 tgl 30-10'])->assertSessionHasNoErrors();

        $this->pay($sale, ['paid_amount' => 1000])->assertSessionHasErrors('paid_amount');
        $add('amox', 4)->assertSessionHasNoErrors(); // total 60 > stok 50
        $this->pay($sale)->assertSessionHas('error', fn ($m) => str_contains($m, 'tidak cukup'));
        $this->assertSame(50, $this->available('amox'));

        $item = $sale->items()->first();
        $this->actingAs($this->kasir)->delete(route('pos.cart.items.destroy', [$sale, $item]));
        $add('amox', 3);
        $this->pay($sale, ['payment_method' => 'non_cash'])->assertSessionHasNoErrors();
        $this->assertSame(20, $this->available('amox'));
    }

    public function test_barcode_scan_adds_one_package(): void
    {
        $sale = $this->tenant(fn () => Sale::create(['type' => 'otc', 'payment_type_id' => $this->umum->id]));
        $barcode = $this->drugs['pct']->packagings->firstWhere('unit_name', 'Strip')->barcode;

        $this->actingAs($this->kasir)->post(route('pos.cart.scan', $sale), ['barcode' => $barcode])->assertSessionHas('success');
        $this->actingAs($this->kasir)->post(route('pos.cart.scan', $sale), ['barcode' => 'TIDAKADA'])->assertSessionHas('error');

        $this->assertSame([2 - 1, 10], [$sale->items()->count(), $sale->items()->first()->quantity_base]);
    }

    public function test_major_alert_at_pos_needs_reason_and_pharmacist_pin(): void
    {
        $this->tenant(fn () => PatientAllergy::create(['patient_id' => $this->patient->id, 'type' => 'drug', 'substance_id' => $this->drugs['amox']->substances->first()->id, 'reaction' => 'Ruam']));
        $sale = $this->tenant(fn () => Sale::create(['type' => 'otc', 'payment_type_id' => $this->umum->id, 'patient_id' => $this->patient->id, 'external_rx_ref' => 'dr. X']));
        $this->actingAs($this->kasir)->post(route('pos.cart.items.store', $sale), ['drug_id' => $this->drugs['amox']->id, 'drug_packaging_id' => $this->drugs['amox']->packagings->firstWhere('unit_name', 'Strip')->id, 'quantity' => 1]);
        $key = 'allergy:'.$this->drugs['amox']->substances->first()->id.':'.$this->drugs['amox']->id;
        $reason = ['overrides' => [$key => 'Pasien sudah uji kulit negatif di RS rujukan.']];

        $this->pay($sale, $reason)->assertSessionHasErrors('approver_pin');
        $this->pay($sale, $reason + ['approver_id' => $this->apoteker->id, 'approver_pin' => '0000'])->assertSessionHasErrors('approver_pin');
        $this->pay($sale, ['approver_id' => $this->apoteker->id, 'approver_pin' => '4321'])->assertSessionHasErrors("overrides.{$key}");
        $this->assertSame(50, $this->available('amox'));

        $this->pay($sale, $reason + ['approver_id' => $this->apoteker->id, 'approver_pin' => '4321'])->assertSessionHasNoErrors();

        $override = $this->tenant(fn () => AlertOverride::sole());
        $this->assertSame(['pos', $this->apoteker->id, $sale->id, $key], [$override->context, $override->user_id, $override->sale_id, $override->alert_key]);
    }

    public function test_pharmacist_approves_major_alert_at_verification(): void
    {
        $this->tenant(fn () => PatientAllergy::create(['patient_id' => $this->patient->id, 'type' => 'drug', 'substance_id' => $this->drugs['amox']->substances->first()->id, 'reaction' => 'Ruam']));
        // Dokter meng-override saat meresepkan.
        $encounter = $this->tenant(fn () => app(RegistrationService::class)->register($this->patient, $this->poli, $this->umum, null, $this->kasir));
        $this->actingAs($this->dokter)->post(route('emr.start', $encounter));
        $icd = Icd10::firstOrCreate(['code' => 'J06.9'], ['name_id' => 'ISPA']);
        $this->actingAs($this->dokter)->postJson(route('emr.diagnoses.store', $encounter), ['icd10_id' => $icd->id, 'type' => 'primary']);
        $key = $this->actingAs($this->dokter)->postJson(route('emr.prescription.store', $encounter), [
            'drug_id' => $this->drugs['amox']->id, 'drug_packaging_id' => $this->drugs['amox']->packagings->firstWhere('unit_name', 'Strip')->id, 'quantity' => 1, 'signa' => '3x1',
        ])->json('alerts.0.key');
        $this->actingAs($this->dokter)->put(route('emr.finish', $encounter), ['subjective' => 'Batuk', 'overrides' => [$key => 'Riwayat alergi diragukan, dikonfirmasi pasien.']])->assertSessionHasNoErrors();

        $sale = $this->open($encounter, $this->apoteker);
        $this->actingAs($this->apoteker)->post(route('pos.cart.verify', $sale))->assertSessionHasErrors("overrides.{$key}");
        $this->actingAs($this->apoteker)->post(route('pos.cart.verify', $sale), ['overrides' => [$key => 'Sudah konfirmasi dokter via telepon.']])->assertSessionHasNoErrors();

        // Kasir membayar tanpa perlu PIN lagi: peringatan sudah disetujui apoteker.
        $this->pay($sale)->assertSessionHasNoErrors();
        $this->assertSame(['prescribe', 'dispense'], $this->tenant(fn () => AlertOverride::orderBy('id')->pluck('context')->all()));
    }

    public function test_only_pharmacist_removes_prescription_items(): void
    {
        $sale = $this->open($this->encounter(['pct' => 1]));
        $item = $sale->items->firstWhere('kind', 'drug');
        $service = $sale->items->firstWhere('kind', 'service');

        $this->actingAs($this->kasir)->delete(route('pos.cart.items.destroy', [$sale, $item]))->assertSessionHas('error');
        $this->actingAs($this->apoteker)->delete(route('pos.cart.items.destroy', [$sale, $service]))->assertSessionHas('error');
        $this->actingAs($this->apoteker)->delete(route('pos.cart.items.destroy', [$sale, $item]))->assertSessionHas('success');
        $this->assertSame(1, $sale->items()->count());
    }

    public function test_void_returns_stock_and_reopens_encounter(): void
    {
        $encounter = $this->encounter(['pct' => 2]);
        $sale = $this->open($encounter);
        $this->actingAs($this->apoteker)->post(route('pos.cart.verify', $sale));
        $this->pay($sale);
        $this->assertSame(95, $this->available('pct'));

        $this->actingAs($this->kasir)->post(route('pos.sales.void', $sale), ['void_reason' => ''])->assertSessionHasErrors('void_reason');
        $this->actingAs($this->kasir)->post(route('pos.sales.void', $sale), ['void_reason' => 'Salah pilih jenis bayar'])->assertSessionHasNoErrors();

        $this->assertSame([15, 100], [$this->qty('P-SOON'), $this->qty('P-LATE')]);
        $this->assertSame(Sale::VOIDED, $sale->fresh()->status);
        $this->assertSame(EncounterStatus::ReadyBilling, $encounter->fresh()->status);
        $this->assertSame(PrescriptionStatus::Prepared, $this->tenant(fn () => Prescription::first()->status));

        // Bisa ditagih ulang dengan keranjang baru.
        $again = $this->open($encounter);
        $this->assertNotSame($sale->id, $again->id);
        $this->pay($again)->assertSessionHasNoErrors();
        $this->assertSame(EncounterStatus::Completed, $encounter->fresh()->status);
    }

    public function test_void_of_psychotropic_by_cashier_needs_pharmacist_pin(): void
    {
        $sale = $this->tenant(fn () => Sale::create(['type' => 'otc', 'payment_type_id' => $this->umum->id, 'external_rx_ref' => 'dr. Y R/9']));
        $this->actingAs($this->kasir)->post(route('pos.cart.items.store', $sale), ['drug_id' => $this->drugs['dzp']->id, 'drug_packaging_id' => $this->drugs['dzp']->packagings->firstWhere('unit_name', 'Tablet')->id, 'quantity' => 5]);
        $this->pay($sale)->assertSessionHasNoErrors();

        $this->actingAs($this->kasir)->post(route('pos.sales.void', $sale), ['void_reason' => 'Pembeli batal'])->assertSessionHasErrors('approver_pin');
        $this->actingAs($this->kasir)->post(route('pos.sales.void', $sale), ['void_reason' => 'Pembeli batal', 'approver_id' => $this->apoteker->id, 'approver_pin' => '4321'])->assertSessionHasNoErrors();

        $this->assertSame($this->apoteker->id, $sale->fresh()->void_approved_by);
        $this->assertSame(20, $this->qty('D-1'));
    }

    public function test_return_sellable_and_quarantine_with_pin(): void
    {
        $sale = $this->tenant(fn () => Sale::create(['type' => 'otc', 'payment_type_id' => $this->umum->id]));
        $this->actingAs($this->kasir)->post(route('pos.cart.items.store', $sale), ['drug_id' => $this->drugs['pct']->id, 'drug_packaging_id' => $this->drugs['pct']->packagings->firstWhere('unit_name', 'Strip')->id, 'quantity' => 2]);
        $this->pay($sale);
        [$soon, $late] = $sale->fresh()->items->first()->batches->sortBy('id')->values()->all(); // 15 dari P-SOON, 5 dari P-LATE

        $lines = [$soon->id => ['quantity' => 4, 'condition' => 'sellable'], $late->id => ['quantity' => 2, 'condition' => 'quarantine']];
        $this->actingAs($this->kasir)->post(route('pos.sales.return', $sale), ['reason' => 'Salah beli', 'lines' => $lines, 'approver_id' => $this->apoteker->id, 'approver_pin' => '9999'])
            ->assertSessionHasErrors('approver_pin');
        $this->actingAs($this->kasir)->post(route('pos.sales.return', $sale), ['reason' => 'Salah beli', 'lines' => [$late->id => ['quantity' => 6, 'condition' => 'sellable']], 'approver_id' => $this->apoteker->id, 'approver_pin' => '4321'])
            ->assertSessionHasErrors("lines.{$late->id}.quantity");

        $this->actingAs($this->kasir)->post(route('pos.sales.return', $sale), ['reason' => 'Salah beli, 1 strip penyok', 'lines' => $lines, 'approver_id' => $this->apoteker->id, 'approver_pin' => '4321'])
            ->assertSessionHasNoErrors();

        $this->assertSame([4, 95], [$this->qty('P-SOON'), $this->qty('P-LATE')]);
        $quarantine = $this->tenant(fn () => Batch::where('status', 'quarantine')->sole());
        $this->assertSame(['P-LATE', 2], [$quarantine->batch_number, $quarantine->qty_on_hand]);
        $this->assertSame(99, $this->available('pct')); // karantina tidak dihitung tersedia

        $return = $sale->returns()->sole();
        $this->assertSame([6 * 300, $this->apoteker->id], [$return->refund_amount, $return->approved_by]);
        $this->assertSame(6, $sale->items()->first()->returned_base);

        // Setelah ada retur, void tidak lagi diizinkan.
        $this->actingAs($this->kasir)->post(route('pos.sales.void', $sale), ['void_reason' => 'Coba void'])->assertSessionHas('error');
    }

    public function test_pages_render_and_other_tenant_blocked(): void
    {
        $encounter = $this->encounter(['pct' => 1]);
        $this->actingAs($this->kasir)->get(route('pos.index'))->assertOk()->assertSee($this->patient->name);

        $sale = $this->open($encounter);
        $this->actingAs($this->kasir)->get(route('pos.cart', $sale))->assertOk()->assertSee('P-SOON')->assertSee('Menunggu verifikasi apoteker');
        $this->actingAs($this->apoteker)->get(route('pos.cart', $sale))->assertOk()->assertSee('Verifikasi & siapkan obat', false);
        $this->actingAs($this->kasir)->get(route('pos.labels', $sale))->assertOk()->assertSee('3 x 1 tablet');

        $this->actingAs($this->apoteker)->post(route('pos.cart.verify', $sale));
        $this->pay($sale);
        $this->actingAs($this->kasir)->get(route('pos.cart', $sale))->assertRedirect(route('pos.sales.show', $sale));
        $this->actingAs($this->kasir)->get(route('pos.sales.show', $sale))->assertOk()->assertSee('Retur obat');
        $this->actingAs($this->kasir)->get(route('pos.sales.receipt', $sale))->assertOk()->assertSee($sale->fresh()->number);
        $this->actingAs($this->kasir)->get(route('pos.sales.index'))->assertOk()->assertSee($sale->fresh()->number);

        $intruder = $this->makeUser(Role::Kasir);
        $this->actingAs($intruder)->get(route('pos.sales.show', $sale))->assertNotFound();
        $this->actingAs($intruder)->post(route('pos.open-encounter', $encounter))->assertNotFound();

        $this->actingAs($this->dokter)->get(route('pos.index'))->assertForbidden();
    }
}
