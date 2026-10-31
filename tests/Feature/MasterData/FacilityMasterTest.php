<?php

namespace Tests\Feature\MasterData;

use App\Modules\Cds\Models\InteractionRule;
use App\Modules\Facility\Models\MedicalService;
use App\Modules\Facility\Models\PaymentType;
use App\Modules\Facility\Models\Poli;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Services\TenantProvisioner;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilityMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_provisioning_creates_default_master_data(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $tenant = app(TenantProvisioner::class)->provision(
            ['name' => 'Klinik Uji', 'code' => 'UJI', 'type' => 'klinik', 'plan' => 'basic', 'status' => 'active'],
            ['name' => 'Admin', 'email' => 'a@uji.id', 'password' => 'password', 'role' => Role::Admin],
        );

        app(TenantContext::class)->runAs($tenant->id, function () {
            $this->assertSame(['UMUM', 'BPJS', 'ASURANSI', 'GRATIS'], PaymentType::ordered()->pluck('code')->all());
            $this->assertFalse(PaymentType::where('code', 'BPJS')->first()->patient_pays);
            $this->assertTrue(Poli::where('code', 'UMUM')->exists());
            $this->assertSame(count(require database_path('data/interaction_templates.php')), InteractionRule::count());
        });
    }

    public function test_admin_manages_poli(): void
    {
        $admin = $this->makeUser(Role::Admin);

        $this->actingAs($admin)->post(route('settings.polis.store'), ['code' => 'gigi', 'name' => 'Poli Gigi', 'is_active' => 1])
            ->assertRedirect(route('settings.polis.index'));

        $poli = Poli::where('code', 'GIGI')->firstOrFail();
        $this->assertSame($admin->tenant_id, $poli->tenant_id);

        $this->actingAs($admin)->post(route('settings.polis.store'), ['code' => 'GIGI', 'name' => 'Duplikat', 'is_active' => 1])
            ->assertSessionHasErrors('code');

        $this->actingAs($admin)->put(route('settings.polis.update', $poli), ['code' => 'GIGI', 'name' => 'Poli Gigi & Mulut', 'is_active' => 0]);
        $this->assertFalse($poli->fresh()->is_active);
    }

    public function test_same_code_allowed_in_other_tenant(): void
    {
        $a = $this->makeUser(Role::Admin);
        $b = $this->makeUser(Role::Admin);

        $this->actingAs($a)->post(route('settings.polis.store'), ['code' => 'KIA', 'name' => 'KIA A', 'is_active' => 1]);
        $this->actingAs($b)->post(route('settings.polis.store'), ['code' => 'KIA', 'name' => 'KIA B', 'is_active' => 1])
            ->assertSessionHasNoErrors();
    }

    public function test_route_binding_does_not_resolve_other_tenant_records(): void
    {
        $owner = $this->makeUser(Role::Admin);
        $intruder = $this->makeUser(Role::Admin);

        $poli = app(TenantContext::class)->runAs($owner->tenant_id, fn () => Poli::create(['code' => 'X', 'name' => 'Poli X']));

        $this->actingAs($intruder)->get(route('settings.polis.edit', $poli))->assertNotFound();
        $this->actingAs($intruder)->put(route('settings.polis.update', $poli), ['code' => 'X', 'name' => 'Diretas', 'is_active' => 1])->assertNotFound();
        $this->assertSame('Poli X', $poli->fresh()->name);
    }

    public function test_last_active_payment_type_cannot_be_deactivated(): void
    {
        $admin = $this->makeUser(Role::Admin);
        $type = app(TenantContext::class)->runAs($admin->tenant_id, fn () => PaymentType::create(['code' => 'UMUM', 'name' => 'Umum', 'patient_pays' => true]));

        $this->actingAs($admin)->put(route('settings.payment-types.update', $type), [
            'code' => 'UMUM', 'name' => 'Umum', 'sort_order' => 1, 'patient_pays' => 1, 'is_active' => 0,
        ])->assertSessionHasErrors('is_active');

        $this->assertTrue($type->fresh()->is_active);
    }

    public function test_medical_service_with_prices_per_payment_type(): void
    {
        $admin = $this->makeUser(Role::Admin);
        [$umum, $bpjs] = app(TenantContext::class)->runAs($admin->tenant_id, fn () => [
            PaymentType::create(['code' => 'UMUM', 'name' => 'Umum', 'patient_pays' => true]),
            PaymentType::create(['code' => 'BPJS', 'name' => 'BPJS', 'patient_pays' => false]),
        ]);

        $this->actingAs($admin)->post(route('settings.services.store'), [
            'code' => 'kons', 'name' => 'Konsultasi', 'is_active' => 1,
            'prices' => [$umum->id => 50000, $bpjs->id => ''],
        ])->assertRedirect(route('settings.services.index'));

        $service = MedicalService::with('prices')->where('code', 'KONS')->firstOrFail();
        $this->assertSame(50000, $service->priceFor($umum));
        $this->assertNull($service->priceFor($bpjs));

        $this->actingAs($admin)->put(route('settings.services.update', $service), [
            'code' => 'KONS', 'name' => 'Konsultasi', 'is_active' => 1,
            'prices' => [$umum->id => '', $bpjs->id => 40000],
        ]);

        $service->load('prices');
        $this->assertNull($service->priceFor($umum));
        $this->assertSame(40000, $service->priceFor($bpjs));
    }
}
