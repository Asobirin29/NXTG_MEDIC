<?php

namespace Database\Seeders;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\PlatformAdmin;
use App\Modules\Identity\Services\TenantProvisioner;
use Illuminate\Database\Seeder;

/**
 * Data demo untuk pengembangan lokal. Semua akun demo memakai password
 * "password" dan PIN "1234" — jangan dipakai di produksi.
 */
class DatabaseSeeder extends Seeder
{
    public function run(TenantProvisioner $provisioner): void
    {
        $this->call(ReferenceDataSeeder::class);

        PlatformAdmin::create([
            'name' => 'Super Admin',
            'email' => 'platform@nxtg.test',
            'password' => 'password',
        ]);

        $tenant = $provisioner->provision(
            ['name' => 'Klinik Pratama Sehat Sentosa', 'code' => 'KPSS', 'type' => 'klinik', 'plan' => 'pro', 'status' => 'active',
                'address' => 'Jl. Merdeka No. 10, Bandung', 'phone' => '022-1234567'],
            ['name' => 'Admin Klinik', 'email' => 'admin@sehat.test', 'password' => 'password', 'role' => Role::Admin, 'pin' => '1234'],
        );

        $staff = [
            ['Siti Rahma', 'kasir@sehat.test', Role::Kasir, null],
            ['Ns. Dewi Lestari', 'perawat@sehat.test', Role::Perawat, 'STR-PRW-2026-001'],
            ['dr. Andi Pratama', 'dokter@sehat.test', Role::Dokter, 'SIP-DU-2026-014'],
            ['apt. Budi Santoso', 'apoteker@sehat.test', Role::Apoteker, 'SIPA-2026-007'],
        ];

        foreach ($staff as [$name, $email, $role, $license]) {
            $tenant->users()->create([
                'name' => $name, 'email' => $email, 'role' => $role, 'license_number' => $license,
                'password' => 'password', 'pin' => '1234', 'is_active' => true,
            ]);
        }

        $provisioner->provision(
            ['name' => 'Puskesmas Sukamaju', 'code' => 'PKM-SKMJ', 'type' => 'puskesmas', 'plan' => 'basic', 'status' => 'active'],
            ['name' => 'Admin Puskesmas', 'email' => 'admin@sukamaju.test', 'password' => 'password', 'role' => Role::Admin],
        );

        $this->call([DemoClinicSeeder::class, DemoStockSeeder::class, DemoPatientSeeder::class, DemoSalesSeeder::class]);
    }
}
