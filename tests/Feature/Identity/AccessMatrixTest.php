<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Matriks hak akses PRD §7 pada level route. */
class AccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    private const ROUTES = [
        'registration.index' => [Role::Admin, Role::Kasir, Role::Perawat],
        'registration.create' => [Role::Admin, Role::Kasir, Role::Perawat],
        'patients.create' => [Role::Admin, Role::Kasir, Role::Perawat],
        'emr.index' => [Role::Perawat, Role::Dokter],
        'pos.index' => [Role::Apoteker, Role::Kasir],
        'patients.index' => [Role::Admin, Role::Kasir, Role::Perawat, Role::Dokter, Role::Apoteker],
        'drugs.index' => [Role::Apoteker],
        'drugs.create' => [Role::Apoteker],
        'substances.index' => [Role::Apoteker],
        'settings.polis.index' => [Role::Admin],
        'settings.payment-types.index' => [Role::Admin],
        'settings.services.index' => [Role::Admin],
        'receipts.index' => [Role::Apoteker],
        'receipts.create' => [Role::Apoteker],
        'stock.index' => [Role::Apoteker],
        'stock.opname' => [Role::Apoteker],
        'suppliers.index' => [Role::Apoteker],
        'interactions.index' => [Role::Apoteker],
        'reports.index' => [Role::Admin, Role::Apoteker, Role::Kasir, Role::Dokter],
        'settings.users.index' => [Role::Admin],
        'settings.appearance' => [Role::Admin],
        'audit.index' => [Role::Admin],
    ];

    public static function cases(): array
    {
        $cases = [];
        foreach (self::ROUTES as $route => $allowed) {
            foreach (Role::cases() as $role) {
                $cases["{$role->value} → {$route}"] = [$role, $route, in_array($role, $allowed, true)];
            }
        }

        return $cases;
    }

    #[DataProvider('cases')]
    public function test_route_access(Role $role, string $route, bool $allowed): void
    {
        $this->actingAs($this->makeUser($role))
            ->get(route($route))
            ->assertStatus($allowed ? 200 : 403);
    }

    public function test_sidebar_only_shows_permitted_menus(): void
    {
        $this->actingAs($this->makeUser(Role::Kasir))
            ->get(route('pos.index'))
            ->assertSee('Farmasi &amp; Kasir', false)
            ->assertDontSee('Ruang Periksa (RME)')
            ->assertDontSee('Master Obat')
            ->assertDontSee('Audit Log');
    }
}
