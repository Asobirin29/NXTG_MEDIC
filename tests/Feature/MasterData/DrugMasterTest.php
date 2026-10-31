<?php

namespace Tests\Feature\MasterData;

use App\Modules\Facility\Models\PaymentType;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Drug;
use App\Modules\Inventory\Models\Substance;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DrugMasterTest extends TestCase
{
    use RefreshDatabase;

    private User $apoteker;

    private PaymentType $umum;

    private Substance $paracetamol;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apoteker = $this->makeUser(Role::Apoteker);
        $this->paracetamol = Substance::create(['name' => 'Parasetamol']);
        $this->umum = app(TenantContext::class)->runAs($this->apoteker->tenant_id,
            fn () => PaymentType::create(['code' => 'UMUM', 'name' => 'Umum', 'patient_pays' => true]));
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Paracetamol 500 mg', 'kfa_code' => '', 'dosage_form' => 'tablet', 'strength' => '500 mg',
            'category' => 'bebas', 'base_unit' => 'tablet', 'barcode' => '', 'min_stock' => 100, 'is_active' => 1,
            'substances' => [['substance_id' => $this->paracetamol->id, 'strength_value' => '500', 'strength_unit' => 'mg']],
            'packagings' => [
                'a' => ['unit_name' => 'Tablet', 'quantity_in_base' => 1],
                'b' => ['unit_name' => 'Strip', 'quantity_in_base' => 10],
                'c' => ['unit_name' => 'Box', 'quantity_in_base' => 100],
            ],
            'default_packaging' => 'b',
            'prices' => [$this->umum->id => 300],
        ], $overrides);
    }

    public function test_apoteker_creates_drug_with_composition_packaging_and_price(): void
    {
        $this->actingAs($this->apoteker)->post(route('drugs.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('drugs.index'));

        $drug = Drug::with(['substances', 'packagings', 'prices'])->firstOrFail();
        $this->assertSame($this->apoteker->tenant_id, $drug->tenant_id);
        $this->assertSame('Parasetamol 500 mg', $drug->compositionLabel());
        $this->assertSame([1, 10, 100], $drug->packagings->pluck('quantity_in_base')->all());
        $this->assertSame('Strip', $drug->packagings->firstWhere('is_default_sale', true)->unit_name);
        $this->assertSame(300, $drug->priceFor($this->umum));
    }

    public function test_packaging_without_base_unit_is_rejected(): void
    {
        $this->actingAs($this->apoteker)->post(route('drugs.store'), $this->payload([
            'packagings' => [['unit_name' => 'Strip', 'quantity_in_base' => 10]],
        ]))->assertSessionHasErrors('packagings');

        $this->assertSame(0, Drug::count());
    }

    public function test_drug_requires_active_substance(): void
    {
        $this->actingAs($this->apoteker)->post(route('drugs.store'), $this->payload(['substances' => []]))
            ->assertSessionHasErrors('substances');
    }

    public function test_update_renames_and_removes_packaging(): void
    {
        $this->actingAs($this->apoteker)->post(route('drugs.store'), $this->payload());
        $drug = Drug::with('packagings')->firstOrFail();
        [$tablet, $strip, $box] = $drug->packagings->all();

        // Box dihapus, nama "Box" dipakai ulang oleh baris lain sekaligus.
        $this->actingAs($this->apoteker)->put(route('drugs.update', $drug), $this->payload([
            'packagings' => [
                'x' => ['id' => $tablet->id, 'unit_name' => 'Tablet', 'quantity_in_base' => 1],
                'y' => ['id' => $strip->id, 'unit_name' => 'Box', 'quantity_in_base' => 10],
            ],
            'default_packaging' => 'x',
            'prices' => [$this->umum->id => ''],
        ]))->assertRedirect(route('drugs.index'));

        $drug->refresh()->load(['packagings', 'prices']);
        $this->assertSame(['Tablet', 'Box'], $drug->packagings->pluck('unit_name')->all());
        $this->assertSame($strip->id, $drug->packagings->last()->id);
        $this->assertNull($drug->priceFor($this->umum));
    }

    public function test_other_tenant_drug_is_not_reachable(): void
    {
        $this->actingAs($this->apoteker)->post(route('drugs.store'), $this->payload());
        $drug = Drug::firstOrFail();

        $other = $this->makeUser(Role::Apoteker);
        $this->actingAs($other)->get(route('drugs.edit', $drug))->assertNotFound();
        $this->actingAs($other)->get(route('drugs.index'))->assertDontSee('Paracetamol 500 mg');
    }

    public function test_search_matches_substance_name(): void
    {
        $this->actingAs($this->apoteker)->post(route('drugs.store'), $this->payload(['name' => 'Sanmol']));

        $this->actingAs($this->apoteker)->get(route('drugs.index', ['q' => 'parasetamol']))->assertSee('Sanmol');
    }

    public function test_kasir_cannot_manage_drugs(): void
    {
        $kasir = $this->makeUser(Role::Kasir, $this->apoteker->tenant);

        $this->actingAs($kasir)->post(route('drugs.store'), $this->payload())->assertForbidden();
    }

    public function test_tenant_substances_are_private_and_shared_ones_read_only(): void
    {
        $this->actingAs($this->apoteker)->post(route('substances.store'), ['name' => 'Zat Lokal'])->assertSessionHasNoErrors();
        $local = Substance::withoutGlobalScopes()->where('name', 'Zat Lokal')->firstOrFail();
        $this->assertSame($this->apoteker->tenant_id, $local->tenant_id);

        $other = $this->makeUser(Role::Apoteker);
        $this->actingAs($other)->getJson(route('substances.search', ['q' => 'Zat']))->assertJsonMissing(['name' => 'Zat Lokal']);
        $this->actingAs($other)->getJson(route('substances.search', ['q' => 'Paras']))->assertJsonFragment(['name' => 'Parasetamol']);

        $this->actingAs($this->apoteker)->put(route('substances.update', $this->paracetamol), ['name' => 'Ubah'])->assertForbidden();
        $this->actingAs($this->apoteker)->post(route('substances.store'), ['name' => 'Parasetamol'])->assertSessionHasErrors('name');
    }
}
