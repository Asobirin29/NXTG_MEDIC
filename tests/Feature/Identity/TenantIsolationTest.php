<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\AuditLog;
use App\Modules\Identity\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_scope_filters_and_fills_tenant_id(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();
        $context = app(TenantContext::class);

        $context->runAs($a->id, fn () => AuditLog::create(['action' => 'uji-a']));
        // tenant_id dari input diabaikan bila konteks aktif.
        $context->runAs($b->id, fn () => AuditLog::create(['action' => 'uji-b', 'tenant_id' => $a->id]));

        $this->assertSame(['uji-a'], $context->runAs($a->id, fn () => AuditLog::where('action', 'like', 'uji-%')->pluck('action')->all()));
        $this->assertSame(['uji-b'], $context->runAs($b->id, fn () => AuditLog::where('action', 'like', 'uji-%')->pluck('action')->all()));
    }

    public function test_user_list_only_shows_own_tenant(): void
    {
        $admin = $this->makeUser(Role::Admin);
        $own = $this->makeUser(Role::Dokter, $admin->tenant, ['name' => 'Dokter Sendiri']);
        $foreign = $this->makeUser(Role::Dokter, attributes: ['name' => 'Dokter Faskes Lain']);

        $this->actingAs($admin)->get(route('settings.users.index'))
            ->assertSee($own->name)
            ->assertDontSee($foreign->name);
    }

    public function test_cannot_edit_user_of_other_tenant(): void
    {
        $admin = $this->makeUser(Role::Admin);
        $foreign = $this->makeUser(Role::Dokter);

        $this->actingAs($admin)->get(route('settings.users.edit', $foreign))->assertNotFound();
        $this->actingAs($admin)->put(route('settings.users.update', $foreign), [
            'name' => 'Diretas', 'email' => $foreign->email, 'role' => 'admin', 'is_active' => 1,
        ])->assertNotFound();

        $this->assertNotSame('Diretas', $foreign->fresh()->name);
    }

    public function test_audit_log_page_only_shows_own_tenant(): void
    {
        $admin = $this->makeUser(Role::Admin);
        $other = Tenant::factory()->create();
        app(TenantContext::class)->runAs($other->id, fn () => AuditLog::create(['action' => 'rahasia-faskes-lain']));

        $this->actingAs($admin)->get(route('audit.index'))->assertDontSee('rahasia-faskes-lain');
    }
}
