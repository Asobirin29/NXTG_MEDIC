<?php

namespace Tests\Feature\Reporting;

use App\Modules\Facility\Models\PaymentType;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Drug;
use App\Modules\Inventory\Models\Substance;
use App\Modules\Inventory\Models\Supplier;
use App\Modules\Inventory\Services\DrugService;
use App\Modules\Inventory\Services\GoodsReceiptService;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Services\CartService;
use App\Modules\Pos\Services\CheckoutService;
use App\Modules\Pos\Services\SaleReversalService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $apoteker;

    private PaymentType $umum;

    private PaymentType $bpjs;

    /** @var array<string, Drug> */
    private array $drugs = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-20 10:00');

        $this->admin = $this->makeUser(Role::Admin);
        $this->apoteker = $this->makeUser(Role::Apoteker, $this->admin->tenant, ['pin' => '4321']);

        $this->tenant(function () {
            $this->umum = PaymentType::create(['code' => 'UMUM', 'name' => 'Umum', 'patient_pays' => true, 'sort_order' => 1]);
            $this->bpjs = PaymentType::create(['code' => 'BPJS', 'name' => 'BPJS', 'patient_pays' => false, 'sort_order' => 2]);
            $make = fn ($name, $cat) => app(DrugService::class)->save(new Drug,
                ['name' => $name, 'dosage_form' => 'tablet', 'category' => $cat, 'base_unit' => 'tablet', 'min_stock' => 0, 'is_active' => true],
                [['substance_id' => Substance::create(['name' => 'Zat '.$name])->id]],
                [['unit_name' => 'Tablet', 'quantity_in_base' => 1]],
                [$this->umum->id => 500, $this->bpjs->id => 400]);
            $this->drugs = ['pct' => $make('Paracetamol', 'bebas'), 'dzp' => $make('Diazepam', 'psikotropika')];

            // Penerimaan sebelum periode laporan (masuk "stok awal") dan di dalam periode.
            $supplier = Supplier::create(['name' => 'PBF', 'is_active' => true]);
            Carbon::setTestNow('2026-09-25 09:00');
            $this->receive($supplier, 'dzp', 100, 'D1');
            $this->receive($supplier, 'pct', 100, 'P1');
            Carbon::setTestNow('2026-10-05 09:00');
            $this->receive($supplier, 'dzp', 50, 'D2');
        });
    }

    private function tenant(callable $fn): mixed
    {
        return app(TenantContext::class)->runAs($this->admin->tenant_id, $fn);
    }

    private function receive(Supplier $supplier, string $key, int $qty, string $batch): void
    {
        $drug = $this->drugs[$key];
        app(GoodsReceiptService::class)->receive(['supplier_id' => $supplier->id, 'received_at' => today()->toDateString()], [[
            'drug_id' => $drug->id, 'drug_packaging_id' => $drug->packagings->first()->id, 'quantity' => $qty,
            'batch_number' => $batch, 'expiry_date' => '2028-01-01', 'unit_price' => 200,
        ]], $this->apoteker);
    }

    private function sell(string $at, array $items, ?PaymentType $pt = null): Sale
    {
        Carbon::setTestNow($at);

        return $this->tenant(function () use ($items, $pt) {
            $cart = app(CartService::class);
            $sale = $cart->openOtc($this->apoteker);
            $sale->update(['payment_type_id' => ($pt ?? $this->umum)->id, 'external_rx_ref' => 'dr. A R/1']);
            foreach ($items as $key => $qty) {
                $cart->addDrug($sale->fresh('paymentType'), $this->drugs[$key], $this->drugs[$key]->packagings->first(), $qty, null);
            }

            return app(CheckoutService::class)->checkout($sale, ['idempotency_key' => (string) Str::uuid(), 'payment_method' => 'non_cash'], $this->apoteker);
        });
    }

    public function test_sales_usage_and_restricted_reports(): void
    {
        $a = $this->sell('2026-10-10 10:00', ['pct' => 10, 'dzp' => 5]);          // 7.500
        $this->sell('2026-10-11 11:00', ['pct' => 4], $this->bpjs);               // nilai 1.600, tagihan 0
        $voided = $this->sell('2026-10-12 12:00', ['dzp' => 20]);
        Carbon::setTestNow('2026-10-12 13:00');
        $this->tenant(fn () => app(SaleReversalService::class)->void($voided, 'Salah input', $this->apoteker));
        Carbon::setTestNow('2026-10-13 09:00');
        $alloc = $a->items()->where('drug_id', $this->drugs['pct']->id)->first()->batches()->first();
        $this->tenant(fn () => app(SaleReversalService::class)->returnItems($a, [$alloc->id => ['quantity' => 2, 'condition' => 'sellable']], 'Kelebihan', $this->apoteker, $this->apoteker->id, '4321'));
        Carbon::setTestNow('2026-10-20 10:00');

        $period = ['dari' => '2026-10-01', 'sampai' => '2026-10-31'];

        $this->actingAs($this->admin)->get(route('reports.show', ['penjualan'] + $period))->assertOk()
            ->assertViewHas('totals', fn ($t) => [$t['count'], $t['value'], $t['collected'], $t['refund'], $t['voided'], $t['cost']] === [2, 9100, 7500, 1000, 1, 3800.0]);

        $this->actingAs($this->admin)->get(route('reports.show', ['pemakaian'] + $period))->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->mapWithKeys(fn ($r) => [$r['drug']->name => [$r['quantity'], $r['returned'], $r['value']]])->all()
                === ['Paracetamol' => [12, 2, 5600], 'Diazepam' => [5, 0, 2500]]);

        // Diazepam: awal 100 (diterima Sept), masuk 50, keluar 5 (20 di-void), akhir 145.
        $this->actingAs($this->apoteker)->get(route('reports.show', ['obat-terbatas'] + $period))->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->map(fn ($r) => [$r['drug']->name, $r['opening'], $r['in'], $r['out'], $r['correction'], $r['closing']])->all()
                === [['Diazepam', 100, 50, 5, 0, 145]]);

        $this->actingAs($this->admin)->get(route('reports.show', ['penjualan', 'unduh' => 1] + $period))
            ->assertOk()->assertDownload('laporan-penjualan-20261001-20261031.xlsx');
    }

    public function test_access_and_period_validation(): void
    {
        $this->actingAs($this->makeUser(Role::Perawat, $this->admin->tenant))->get(route('reports.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('reports.show', 'tidak-ada'))->assertNotFound();
        $this->actingAs($this->admin)->get(route('reports.show', ['penjualan', 'dari' => '2026-10-10', 'sampai' => '2026-10-01']))->assertSessionHasErrors('sampai');
        $this->actingAs($this->admin)->get(route('reports.show', 'penjualan'))->assertOk()->assertSee('Diterima dari pasien per hari');
    }

    public function test_dashboard_differs_per_role(): void
    {
        $this->sell('2026-10-19 10:00', ['pct' => 2]);
        Carbon::setTestNow('2026-10-20 10:00');

        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()
            ->assertSee('Kunjungan · 14 hari')->assertSee('Rp1.000')->assertDontSee('Resep menunggu verifikasi');
        $this->actingAs($this->apoteker)->get(route('dashboard'))->assertOk()
            ->assertSee('Resep menunggu verifikasi')->assertSee('Stok menipis')->assertDontSee('Kunjungan · 14 hari');
        $this->actingAs($this->makeUser(Role::Dokter, $this->admin->tenant))->get(route('dashboard'))->assertOk()
            ->assertSee('Menunggu dokter')->assertDontSee('Pasien siap bayar');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
