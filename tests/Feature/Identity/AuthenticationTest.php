<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\AuditLog;
use App\Modules\Identity\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public static function homeRoutes(): array
    {
        return [
            'kasir ke pendaftaran' => [Role::Kasir, 'registration.index'],
            'perawat ke ruang periksa' => [Role::Perawat, 'emr.index'],
            'dokter ke ruang periksa' => [Role::Dokter, 'emr.index'],
            'apoteker ke beranda' => [Role::Apoteker, 'dashboard'],
            'admin ke beranda' => [Role::Admin, 'dashboard'],
        ];
    }

    #[DataProvider('homeRoutes')]
    public function test_login_redirects_to_role_home(Role $role, string $home): void
    {
        $user = $this->makeUser($role);

        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route($home));

        $this->assertAuthenticatedAs($user);
        $this->get(route($home))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'login', 'user_id' => $user->id, 'tenant_id' => $user->tenant_id]);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = $this->makeUser(Role::Kasir);

        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'salah'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $user = $this->makeUser(Role::Kasir);

        foreach (range(1, 5) as $_) {
            $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'salah']);
        }

        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_user_is_logged_out(): void
    {
        $user = $this->makeUser(Role::Admin, attributes: ['is_active' => false]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_suspended_tenant_is_logged_out(): void
    {
        $user = $this->makeUser(Role::Admin, Tenant::factory()->suspended()->create());

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_switch_user_with_correct_pin(): void
    {
        $tenant = Tenant::factory()->create();
        $kasir = $this->makeUser(Role::Kasir, $tenant);
        $apoteker = $this->makeUser(Role::Apoteker, $tenant, ['pin' => '4321']);

        $this->actingAs($kasir)
            ->post(route('switch-user.attempt'), ['user_id' => $apoteker->id, 'pin' => '4321'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($apoteker);
        $this->assertSame('switch_user', AuditLog::latest('id')->first()->action);
    }

    public function test_switch_user_rejects_wrong_pin(): void
    {
        $tenant = Tenant::factory()->create();
        $kasir = $this->makeUser(Role::Kasir, $tenant);
        $apoteker = $this->makeUser(Role::Apoteker, $tenant, ['pin' => '4321']);

        $this->actingAs($kasir)
            ->post(route('switch-user.attempt'), ['user_id' => $apoteker->id, 'pin' => '0000'])
            ->assertSessionHasErrors('pin');

        $this->assertAuthenticatedAs($kasir);
    }

    public function test_switch_user_cannot_cross_tenant(): void
    {
        $kasir = $this->makeUser(Role::Kasir);
        $other = $this->makeUser(Role::Apoteker, attributes: ['pin' => '4321']);

        $this->actingAs($kasir)
            ->post(route('switch-user.attempt'), ['user_id' => $other->id, 'pin' => '4321'])
            ->assertSessionHasErrors('pin');

        $this->assertAuthenticatedAs($kasir);
    }
}
