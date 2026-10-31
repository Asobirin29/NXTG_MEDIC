<?php

namespace Database\Seeders;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Drug;
use App\Modules\Inventory\Models\Supplier;
use App\Modules\Inventory\Services\GoodsReceiptService;
use App\Modules\Inventory\Services\StockCorrectionService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Stok contoh faskes demo: beberapa penerimaan bertanggal mundur sehingga
 * terlihat kasus FEFO lintas batch, ED ≤ 90 hari, batch kedaluwarsa yang
 * terblokir, stok menipis, dan obat yang belum pernah diterima (habis).
 */
class DemoStockSeeder extends Seeder
{
    private const SUPPLIERS = [
        ['PBF Sinar Medika', '022-7301122', 'Jl. Soekarno-Hatta No. 45, Bandung'],
        ['PBF Mitra Husada', '022-6012345', 'Jl. Kopo No. 210, Bandung'],
        ['PT Distribusi Farmasi Nusantara', '021-5550199', 'Jl. Gatot Subroto Kav. 12, Jakarta'],
    ];

    /**
     * [hari lalu diterima, pemasok, faktur, [[obat, kemasan, jumlah, batch, ED (hari dari sekarang), harga/kemasan]]]
     */
    private const RECEIPTS = [
        [400, 'PBF Mitra Husada', 'MH/2509/0418', [
            ['Ambroxol Sirup 15 mg/5 ml', 'Botol', 6, 'AMB2409A', -10, 6500],
        ]],
        [150, 'PBF Sinar Medika', 'SM-INV-26-0611', [
            ['Paracetamol 500 mg', 'Box', 3, 'PCT2604A', 200, 18000],
            ['Amoxicillin 500 mg', 'Box', 2, 'AMX2511K', 45, 42000],
            ['Ibuprofen 400 mg', 'Box', 2, 'IBU2603B', 420, 27000],
            ['Amlodipine 10 mg', 'Box', 3, 'AML2512C', 70, 24000],
            ['Metformin 500 mg', 'Box', 3, 'MET2605A', 380, 21000],
            ['Captopril 25 mg', 'Box', 1, 'CAP2601D', 520, 15000],
        ]],
        [20, 'PT Distribusi Farmasi Nusantara', 'DFN/X/2026/0932', [
            ['Paracetamol 500 mg', 'Box', 2, 'PCT2610B', 540, 18500],
            ['Amoxicillin 500 mg', 'Box', 3, 'AMX2609M', 600, 43000],
            ['Paracetamol Sirup 120 mg/5 ml', 'Botol', 12, 'PCS2607A', 330, 5200],
            ['Ambroxol Sirup 15 mg/5 ml', 'Botol', 10, 'AMB2608C', 410, 6800],
            ['Simvastatin 20 mg', 'Box', 2, 'SIM2604E', 460, 30000],
            ['Klaritromisin 500 mg', 'Box', 1, 'KLR2606A', 300, 73000],
            ['Warfarin 2 mg', 'Strip', 2, 'WRF2511B', 400, 10500],
            ['Omeprazole 20 mg', 'Box', 2, 'OMP2605B', 360, 16800],
            ['Cetirizine 10 mg', 'Box', 2, 'CTZ2607A', 500, 14000],
            ['CTM 4 mg', 'Box', 1, 'CTM2603A', 80, 7000],
            ['Antasida DOEN', 'Box', 2, 'ANT2606A', 380, 10500],
            ['Dexamethasone 0,5 mg', 'Box', 1, 'DEX2608B', 650, 10500],
            ['Asam Mefenamat 500 mg', 'Box', 2, 'MEF2609A', 560, 35000],
            ['Ciprofloxacin 500 mg', 'Box', 1, 'CIP2605C', 450, 63000],
            ['Diazepam 5 mg', 'Strip', 3, 'DZP2604A', 520, 4200],
        ]],
    ];

    public function run(GoodsReceiptService $receipts, StockCorrectionService $corrections, TenantContext $context): void
    {
        $tenant = Tenant::where('code', 'KPSS')->firstOrFail();
        $apoteker = $tenant->users()->where('role', Role::Apoteker)->firstOrFail();

        $context->runAs($tenant->id, function () use ($receipts, $corrections, $apoteker) {
            foreach (self::SUPPLIERS as [$name, $phone, $address]) {
                Supplier::create(['name' => $name, 'phone' => $phone, 'address' => $address, 'is_active' => true]);
            }
            $suppliers = Supplier::pluck('id', 'name');
            $drugs = Drug::with('packagings')->get()->keyBy('name');
            $now = now();

            foreach (self::RECEIPTS as [$daysAgo, $supplier, $invoice, $rows]) {
                // Tanggal mundur supaya kartu stok & riwayat terlihat realistis.
                Carbon::setTestNow($now->copy()->subDays($daysAgo)->setTime(9, 30));

                $receipts->receive(
                    ['supplier_id' => $suppliers[$supplier], 'invoice_number' => $invoice, 'received_at' => today()->toDateString()],
                    array_map(fn ($r) => [
                        'drug_id' => $drugs[$r[0]]->id,
                        'drug_packaging_id' => $drugs[$r[0]]->packagings->firstWhere('unit_name', $r[1])->id,
                        'quantity' => $r[2],
                        'batch_number' => $r[3],
                        'expiry_date' => $now->copy()->addDays($r[4])->toDateString(),
                        'unit_price' => $r[5],
                    ], $rows),
                    $apoteker,
                );
            }

            Carbon::setTestNow($now->copy()->subDays(3)->setTime(14, 10));
            $syrup = Batch::where('batch_number', 'PCS2607A')->firstOrFail();
            $corrections->adjust($syrup, -60, 'rusak', '1 botol pecah saat penataan rak', $apoteker);

            Carbon::setTestNow();
        });
    }
}
