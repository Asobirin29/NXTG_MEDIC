<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\AuditLog;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_switch_theme_for_whole_tenant(): void
    {
        $admin = $this->makeUser(Role::Admin);
        $dokter = $this->makeUser(Role::Dokter, $admin->tenant);

        $this->actingAs($admin)->put(route('settings.appearance.update'), ['theme' => 'indigo'])->assertRedirect();
        $this->assertSame('indigo', $admin->tenant->fresh()->theme());

        $this->actingAs($dokter)->get(route('emr.index'))->assertSee('data-nx-brand="indigo"', false);
    }

    public function test_invalid_theme_is_rejected(): void
    {
        $admin = $this->makeUser(Role::Admin);

        $this->actingAs($admin)->put(route('settings.appearance.update'), ['theme' => 'pink'])->assertSessionHasErrors('theme');
        $this->assertSame('teal', $admin->tenant->fresh()->theme());
    }

    public function test_admin_creates_user_in_own_tenant(): void
    {
        $admin = $this->makeUser(Role::Admin);

        $this->actingAs($admin)->post(route('settings.users.store'), [
            'name' => 'dr. Baru', 'email' => 'baru@test.id', 'role' => 'dokter', 'license_number' => 'SIP-1',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123', 'pin' => '2468', 'is_active' => 1,
        ])->assertRedirect(route('settings.users.index'));

        $user = User::where('email', 'baru@test.id')->firstOrFail();
        $this->assertSame($admin->tenant_id, $user->tenant_id);
        $this->assertTrue($user->checkPin('2468'));
    }

    public function test_medical_staff_requires_license_number(): void
    {
        $admin = $this->makeUser(Role::Admin);

        $this->actingAs($admin)->post(route('settings.users.store'), [
            'name' => 'dr. Tanpa SIP', 'email' => 'tanpa@test.id', 'role' => 'dokter',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123', 'is_active' => 1,
        ])->assertSessionHasErrors('license_number');
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $admin = $this->makeUser(Role::Admin);

        $this->actingAs($admin)->put(route('settings.users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'admin', 'is_active' => 0,
        ])->assertSessionHasErrors('role');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_user_changes_are_audited_without_secrets(): void
    {
        $admin = $this->makeUser(Role::Admin);
        $kasir = $this->makeUser(Role::Kasir, $admin->tenant);

        $this->actingAs($admin)->put(route('settings.users.update', $kasir), [
            'name' => 'Nama Baru', 'email' => $kasir->email, 'role' => 'kasir', 'is_active' => 1, 'pin' => '9999',
        ]);

        $log = AuditLog::where('action', 'updated')->where('auditable_id', $kasir->id)->latest('id')->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('Nama Baru', $log->new_values['name']);
        $this->assertSame('[disembunyikan]', $log->new_values['pin']);
    }

    public function test_audit_log_is_immutable(): void
    {
        $log = AuditLog::create(['action' => 'uji', 'tenant_id' => $this->makeUser(Role::Admin)->tenant_id]);

        $this->expectException(LogicException::class);
        $log->update(['action' => 'diubah']);
    }
}
