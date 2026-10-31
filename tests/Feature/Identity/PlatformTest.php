<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\PlatformAdmin;
use App\Modules\Identity\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    private function platformAdmin(): PlatformAdmin
    {
        return PlatformAdmin::create(['name' => 'Owner', 'email' => 'owner@test.id', 'password' => 'password']);
    }

    public function test_tenant_user_cannot_open_platform_panel(): void
    {
        $this->actingAs($this->makeUser(Role::Admin))
            ->get(route('platform.tenants.index'))
            ->assertRedirect(route('platform.login'));
    }

    public function test_platform_admin_creates_tenant_with_first_admin(): void
    {
        $this->actingAs($this->platformAdmin(), 'platform')
            ->post(route('platform.tenants.store'), [
                'name' => 'Klinik Baru', 'code' => 'KB01', 'type' => 'klinik', 'plan' => 'basic',
                'admin_name' => 'Admin Baru', 'admin_email' => 'admin@baru.id', 'admin_password' => 'rahasia123',
            ])->assertRedirect(route('platform.tenants.index'));

        $tenant = Tenant::where('code', 'KB01')->firstOrFail();
        $admin = $tenant->users()->firstOrFail();
        $this->assertSame(Role::Admin, $admin->role);
        $this->assertSame('teal', $tenant->theme());

        $this->post(route('login.attempt'), ['email' => 'admin@baru.id', 'password' => 'rahasia123'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_platform_admin_suspends_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->platformAdmin(), 'platform')
            ->put(route('platform.tenants.update', $tenant), [
                'name' => $tenant->name, 'code' => $tenant->code, 'type' => 'klinik', 'plan' => 'basic', 'status' => 'suspended',
            ])->assertRedirect();

        $this->assertFalse($tenant->fresh()->isActive());
    }
}
