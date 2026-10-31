<?php

namespace Tests\Feature\Inventory;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\AuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Exceptions\InsufficientStock;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Drug;
use App\Modules\Inventory\Models\GoodsReceipt;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Substance;
use App\Modules\Inventory\Models\Supplier;
use App\Modules\Inventory\Services\DrugService;
use App\Modules\Inventory\Services\FefoAllocator;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Inventory\Services\StockLevels;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use LogicException;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;

    private User $apoteker;

    private Drug $drug;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apoteker = $this->makeUser(Role::Apoteker);

        $this->tenant(function () {
            $this->supplier = Supplier::create(['name' => 'PBF Uji', 'is_active' => true]);
            $this->drug = $this->makeDrug('Paracetamol 500', 50);
        });
    }

    private function tenant(callable $fn): mixed
    {
        return app(TenantContext::class)->runAs($this->apoteker->tenant_id, $fn);
    }

    private function makeDrug(string $name, int $min = 0): Drug
    {
        return app(DrugService::class)->save(new Drug,
            ['name' => $name, 'dosage_form' => 'tablet', 'category' => 'bebas', 'base_unit' => 'tablet', 'min_stock' => $min, 'is_active' => true],
            [['substance_id' => Substance::firstOrCreate(['name' => 'Zat '.$name])->id]],
            [['unit_name' => 'Tablet', 'quantity_in_base' => 1], ['unit_name' => 'Strip', 'quantity_in_base' => 10], ['unit_name' => 'Box', 'quantity_in_base' => 100]],
            []);
    }

    private function pack(string $unit, ?Drug $drug = null): int
    {
        return ($drug ?? $this->drug)->packagings()->where('unit_name', $unit)->value('id');
    }

    private function receive(array $items, array $header = []): TestResponse
    {
        return $this->actingAs($this->apoteker)->post(route('receipts.store'), $header + [
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'FK-001',
            'received_at' => today()->toDateString(),
            'items' => array_map(fn ($i) => $i + [
                'drug_id' => $this->drug->id,
                'drug_packaging_id' => $this->pack('Box'),
                'quantity' => 1,
                'batch_number' => 'A1',
                'expiry_date' => today()->addYear()->toDateString(),
                'unit_price' => 20000,
            ], $items),
        ]);
    }

    private function batch(string $number): Batch
    {
        return $this->tenant(fn () => Batch::where('batch_number', $number)->firstOrFail());
    }

    public function test_receipt_converts_packages_to_base_units_and_writes_ledger(): void
    {
        $this->receive([['quantity' => 2, 'batch_number' => 'a1'], ['drug_packaging_id' => $this->pack('Strip'), 'quantity' => 3, 'batch_number' => 'B2', 'unit_price' => 2500]])
            ->assertSessionHasNoErrors()->assertRedirect();

        $receipt = $this->tenant(fn () => GoodsReceipt::with('items')->firstOrFail());
        $this->assertSame('PB-'.now()->year.'-0001', $receipt->number);
        $this->assertSame(2 * 20000 + 3 * 2500, $receipt->total_cost);

        $a1 = $this->batch('A1'); // nomor batch dinormalisasi ke huruf besar
        $this->assertSame(200, $a1->qty_on_hand);
        $this->assertSame('200.0000', $a1->purchase_price_per_base);
        $this->assertSame(30, $this->batch('B2')->qty_on_hand);

        $movement = $this->tenant(fn () => StockMovement::where('batch_id', $a1->id)->sole());
        $this->assertSame([StockMovementType::Receipt, 200, 200], [$movement->type, $movement->quantity, $movement->balance_after]);
        $this->assertSame([GoodsReceipt::class, $receipt->id], [$movement->reference_type, $movement->reference_id]);
    }

    public function test_same_batch_received_again_adds_balance_with_weighted_cost(): void
    {
        $this->receive([['quantity' => 1, 'unit_price' => 10000]]);   // 100 @ Rp100
        $this->receive([['quantity' => 1, 'unit_price' => 30000]]);   // 100 @ Rp300

        $batch = $this->batch('A1');
        $this->assertSame(200, $batch->qty_on_hand);
        $this->assertSame('200.0000', $batch->purchase_price_per_base);
        $this->assertSame([100, 200], $this->tenant(fn () => StockMovement::orderBy('id')->pluck('balance_after')->all()));
    }

    public function test_same_batch_with_different_expiry_is_rejected_and_rolled_back(): void
    {
        $this->receive([[]]);
        $this->receive([['batch_number' => 'NEW'], ['expiry_date' => today()->addYears(2)->toDateString()]])
            ->assertSessionHasErrors('items.1.expiry_date');

        $this->assertSame(1, $this->tenant(fn () => GoodsReceipt::count()));
        $this->assertFalse($this->tenant(fn () => Batch::where('batch_number', 'NEW')->exists()));
        $this->assertSame(100, $this->batch('A1')->qty_on_hand);
    }

    public function test_receipt_validation(): void
    {
        $this->receive([['expiry_date' => today()->toDateString()]])->assertSessionHasErrors('items.0.expiry_date');
        $this->receive([[], ['quantity' => 2]])->assertSessionHasErrors('items');
        $this->receive([[]], ['received_at' => today()->addDay()->toDateString()])->assertSessionHasErrors('received_at');

        $other = $this->tenant(fn () => $this->makeDrug('Lain'));
        $this->receive([['drug_packaging_id' => $this->pack('Box', $other)]])->assertSessionHasErrors('items.0.drug_packaging_id');

        $this->assertSame(0, $this->tenant(fn () => Batch::count()));
    }

    public function test_adjustment_requires_reason_and_cannot_go_negative(): void
    {
        $this->receive([[]]);
        $batch = $this->batch('A1');
        $url = route('stock.adjust', $batch);
        $base = ['direction' => 'out', 'quantity' => 3, 'drug_packaging_id' => $this->pack('Strip'), 'reason_code' => 'rusak'];

        $this->actingAs($this->apoteker)->post($url, $base)->assertSessionHasErrors('note');
        $this->actingAs($this->apoteker)->post($url, ['quantity' => 2, 'drug_packaging_id' => $this->pack('Box')] + $base + ['note' => 'Hitung ulang'])
            ->assertSessionHas('error');
        $this->assertSame(100, $batch->fresh()->qty_on_hand);

        $this->actingAs($this->apoteker)->post($url, $base + ['note' => 'Strip sobek kena air'])->assertSessionHasNoErrors();

        $this->assertSame(70, $batch->fresh()->qty_on_hand);
        $movement = $this->tenant(fn () => StockMovement::where('type', 'adjustment')->sole());
        $this->assertSame([-30, 70, $this->apoteker->id], [$movement->quantity, $movement->balance_after, $movement->user_id]);
        $this->assertStringContainsString('Strip sobek kena air', $movement->reason);
        $this->assertTrue(AuditLog::where('action', 'stock.adjusted')->where('reason', $movement->reason)->exists());
    }

    public function test_ledger_is_append_only_in_model_and_database(): void
    {
        $this->receive([[]]);
        $movement = $this->tenant(fn () => StockMovement::firstOrFail());

        try {
            $movement->update(['quantity' => 999]);
            $this->fail('Update mutasi seharusnya ditolak.');
        } catch (LogicException) {
        }

        $this->expectException(QueryException::class);
        DB::table('stock_movements')->where('id', $movement->id)->delete();
    }

    public function test_database_rejects_negative_batch_balance(): void
    {
        $this->receive([[]]);

        $this->expectException(QueryException::class);
        DB::table('batches')->update(['qty_on_hand' => DB::raw('qty_on_hand - 101')]);
    }

    public function test_opname_records_only_differences(): void
    {
        $this->receive([['batch_number' => 'A1'], ['batch_number' => 'B2', 'expiry_date' => today()->addYears(2)->toDateString()]]);
        $a1 = $this->batch('A1');
        $b2 = $this->batch('B2');

        $this->actingAs($this->apoteker)->post(route('stock.opname.store'), ['reason' => 'Opname akhir bulan', 'counts' => [
            $a1->id => ['system' => 100, 'counted' => 97],
            $b2->id => ['system' => 100, 'counted' => 100],
        ]])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame([97, 100], [$a1->fresh()->qty_on_hand, $b2->fresh()->qty_on_hand]);
        $opnameMoves = $this->tenant(fn () => StockMovement::where('type', 'opname')->get());
        $this->assertCount(1, $opnameMoves);
        $this->assertSame(-3, $opnameMoves->first()->quantity);
    }

    public function test_opname_rejected_when_balance_changed_since_sheet_opened(): void
    {
        $this->receive([[]]);
        $batch = $this->batch('A1');

        $this->actingAs($this->apoteker)->post(route('stock.opname.store'), ['reason' => 'Opname rak A', 'counts' => [
            $batch->id => ['system' => 120, 'counted' => 90],
        ]])->assertSessionHas('error');

        $this->assertSame(100, $batch->fresh()->qty_on_hand);
        $this->assertSame(0, $this->tenant(fn () => StockMovement::where('type', 'opname')->count()));

        $this->actingAs($this->apoteker)->post(route('stock.opname.store'), ['reason' => 'Opname rak A', 'counts' => [
            $batch->id => ['system' => 100, 'counted' => ''],
        ]])->assertSessionHasErrors('counts');
    }

    public function test_fefo_issue_spans_batches_and_skips_expired(): void
    {
        $this->receive([
            ['batch_number' => 'LATE', 'expiry_date' => today()->addYears(2)->toDateString()],
            ['batch_number' => 'SOON', 'drug_packaging_id' => $this->pack('Strip'), 'quantity' => 2, 'expiry_date' => today()->addMonth()->toDateString()],
        ]);
        // Batch yang ED-nya sudah lewat (diterima jauh sebelumnya).
        $this->receive([['batch_number' => 'OLD', 'expiry_date' => today()->subDay()->toDateString()]], ['received_at' => today()->subYear()->toDateString()]);

        $levels = app(StockLevels::class);
        $this->assertSame([$this->drug->id => 120], $this->tenant(fn () => $levels->available([$this->drug->id])));

        $this->tenant(fn () => DB::transaction(function () {
            $allocation = app(FefoAllocator::class)->allocate($this->drug, 50);
            $this->assertSame(['SOON' => 20, 'LATE' => 30], collect($allocation)->mapWithKeys(fn ($r) => [$r['batch']->batch_number => $r['quantity']])->all());
            app(StockLedger::class)->issue($allocation, null, $this->apoteker);
        }));

        $this->assertSame([0, 70, 100], [$this->batch('SOON')->qty_on_hand, $this->batch('LATE')->qty_on_hand, $this->batch('OLD')->qty_on_hand]);

        $this->expectException(InsufficientStock::class);
        $this->tenant(fn () => DB::transaction(fn () => app(FefoAllocator::class)->allocate($this->drug, 71)));
    }

    public function test_alerts_low_stock_near_expiry_and_expired(): void
    {
        $this->receive([['batch_number' => 'SOON', 'drug_packaging_id' => $this->pack('Strip'), 'quantity' => 4, 'expiry_date' => today()->addDays(60)->toDateString()]]);
        $this->receive([['batch_number' => 'OLD', 'expiry_date' => today()->subDays(5)->toDateString()]], ['received_at' => today()->subYear()->toDateString()]);
        $this->tenant(fn () => $this->makeDrug('Belum Pernah Datang'));

        $levels = app(StockLevels::class);
        $low = $this->tenant(fn () => $levels->lowStock()->pluck('available_stock', 'name')->all());
        $this->assertSame(['Belum Pernah Datang' => 0, 'Paracetamol 500' => 40], $low);
        $this->assertSame(['SOON'], $this->tenant(fn () => $levels->nearExpiry()->pluck('batch_number')->all()));
        $this->assertSame(['OLD'], $this->tenant(fn () => $levels->expired()->pluck('batch_number')->all()));

        $this->actingAs($this->apoteker)->get(route('stock.index', ['filter' => 'expired']))->assertOk()->assertSee('OLD')->assertDontSee('SOON');
        $this->actingAs($this->apoteker)->get(route('stock.index'))->assertOk()->assertSee('Menipis')->assertSee('Habis');
        $this->actingAs($this->apoteker)->get(route('dashboard'))->assertOk()->assertSee('Stok menipis')->assertSee('SOON');

        $kasir = $this->makeUser(Role::Kasir, $this->apoteker->tenant);
        $this->actingAs($kasir)->get(route('dashboard'))->assertOk()->assertDontSee('Stok menipis');
    }

    public function test_stock_card_and_pages_render(): void
    {
        $this->receive([[]]);
        $receipt = $this->tenant(fn () => GoodsReceipt::firstOrFail());

        $this->actingAs($this->apoteker)->get(route('stock.show', $this->drug))->assertOk()
            ->assertSee('Keluar berikutnya')->assertSee($receipt->number)->assertSee('1 Box');
        $this->actingAs($this->apoteker)->get(route('receipts.show', $receipt))->assertOk()->assertSee('A1');
        $this->actingAs($this->apoteker)->get(route('receipts.create'))->assertOk();
        $this->actingAs($this->apoteker)->get(route('stock.opname'))->assertOk()->assertSee('A1');
    }

    public function test_drug_search_shows_available_stock(): void
    {
        $this->receive([['drug_packaging_id' => $this->pack('Strip'), 'quantity' => 12]]);

        $this->actingAs($this->apoteker)->getJson(route('drugs.search', ['q' => 'para']))
            ->assertOk()->assertJsonPath('0.stock', 120)->assertJsonPath('0.stock_label', '1 Box + 2 Strip (120 tablet)');
    }

    public function test_other_tenant_cannot_touch_batches(): void
    {
        $this->receive([[]]);
        $batch = $this->batch('A1');
        $intruder = $this->makeUser(Role::Apoteker);

        $this->actingAs($intruder)->get(route('stock.show', $this->drug))->assertNotFound();
        $this->actingAs($intruder)->post(route('stock.adjust', $batch), [
            'direction' => 'out', 'quantity' => 1, 'drug_packaging_id' => $this->pack('Tablet'), 'reason_code' => 'hilang', 'note' => 'coba-coba',
        ])->assertNotFound();
        $this->actingAs($intruder)->post(route('receipts.store'), [
            'supplier_id' => $this->supplier->id, 'received_at' => today()->toDateString(), 'items' => [[
                'drug_id' => $this->drug->id, 'drug_packaging_id' => $this->pack('Box'), 'quantity' => 1, 'batch_number' => 'X', 'expiry_date' => today()->addYear()->toDateString(), 'unit_price' => 1,
            ]],
        ])->assertSessionHasErrors(['supplier_id', 'items.0.drug_id']);

        $this->assertSame(100, $batch->fresh()->qty_on_hand);
    }

    public function test_only_pharmacist_manages_stock(): void
    {
        $dokter = $this->makeUser(Role::Dokter, $this->apoteker->tenant);

        $this->actingAs($dokter)->get(route('stock.index'))->assertForbidden();
        $this->actingAs($dokter)->get(route('receipts.create'))->assertForbidden();
        $this->actingAs($dokter)->post(route('stock.opname.store'), [])->assertForbidden();
    }
}
