<?php

namespace Database\Seeders;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Inventory\Models\Drug;
use App\Modules\Pos\Services\CartService;
use App\Modules\Pos\Services\CheckoutService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** Penjualan bebas (OTC) contoh 13 hari terakhir agar grafik beranda & laporan berisi. */
class DemoSalesSeeder extends Seeder
{
    /** [nama obat, kemasan, jumlah] — dipilih bergantian per transaksi. */
    private const BASKETS = [
        [['Paracetamol 500 mg', 'Strip', 1]],
        [['Antasida DOEN', 'Strip', 2]],
        [['Cetirizine 10 mg', 'Strip', 1], ['Paracetamol 500 mg', 'Strip', 1]],
        [['Paracetamol Sirup 120 mg/5 ml', 'Botol', 1]],
        [['Ibuprofen 400 mg', 'Strip', 1]],
        [['CTM 4 mg', 'Strip', 1], ['Antasida DOEN', 'Strip', 1]],
    ];

    public function run(CartService $cart, CheckoutService $checkout, TenantContext $context): void
    {
        $tenant = Tenant::where('code', 'KPSS')->firstOrFail();
        $kasir = $tenant->users()->where('role', Role::Kasir)->firstOrFail();
        $now = now();

        $context->runAs($tenant->id, function () use ($cart, $checkout, $kasir, $now) {
            $drugs = Drug::with('packagings')->get()->keyBy('name');
            $n = 0;

            foreach (range(13, 1) as $daysAgo) {
                $perDay = 1 + ($daysAgo * 7) % 4; // 1–4 transaksi, bervariasi tapi tetap deterministik
                foreach (range(1, $perDay) as $k) {
                    Carbon::setTestNow($now->copy()->subDays($daysAgo)->setTime(8 + $k * 2, 15));
                    $sale = $cart->openOtc($kasir);
                    foreach (self::BASKETS[$n++ % count(self::BASKETS)] as [$name, $unit, $qty]) {
                        $cart->addDrug($sale, $drugs[$name], $drugs[$name]->packagings->firstWhere('unit_name', $unit), $qty, null);
                    }
                    $checkout->checkout($sale, ['idempotency_key' => (string) Str::uuid(), 'payment_method' => $k % 2 ? 'cash' : 'non_cash', 'paid_amount' => 100000], $kasir);
                }
            }

            Carbon::setTestNow();
        });
    }
}
