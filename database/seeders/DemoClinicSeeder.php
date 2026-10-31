<?php

namespace Database\Seeders;

use App\Modules\Facility\Models\MedicalService;
use App\Modules\Facility\Models\MedicalServicePrice;
use App\Modules\Facility\Models\PaymentType;
use App\Modules\Facility\Models\Poli;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Inventory\Models\Drug;
use App\Modules\Inventory\Models\Substance;
use App\Modules\Inventory\Services\DrugService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Obat & tindakan contoh untuk faskes demo. Kode KFA sengaja dikosongkan:
 * diisi dari kamus KFA resmi saat impor, bukan dikarang.
 */
class DemoClinicSeeder extends Seeder
{
    /** [nama, sediaan, kekuatan, kategori, satuan terkecil, [[zat, nilai, unit]], [[kemasan, isi]], harga UMUM per satuan terkecil, stok minimum] */
    private const DRUGS = [
        ['Paracetamol 500 mg', 'tablet', '500 mg', 'bebas', 'tablet', [['Parasetamol', 500, 'mg']], [['Strip', 10], ['Box', 100]], 300, 200],
        ['Paracetamol Sirup 120 mg/5 ml', 'sirup', '120 mg/5 ml', 'bebas', 'ml', [['Parasetamol', 24, 'mg/ml']], [['Botol', 60]], 120, 600],
        ['Ibuprofen 400 mg', 'tablet', '400 mg', 'bebas_terbatas', 'tablet', [['Ibuprofen', 400, 'mg']], [['Strip', 10], ['Box', 100]], 450, 100],
        ['Asam Mefenamat 500 mg', 'kaplet', '500 mg', 'keras', 'kaplet', [['Asam Mefenamat', 500, 'mg']], [['Strip', 10], ['Box', 100]], 500, 100],
        ['Amoxicillin 500 mg', 'kapsul', '500 mg', 'keras', 'kapsul', [['Amoksisilin', 500, 'mg']], [['Strip', 10], ['Box', 100]], 700, 200],
        ['Ciprofloxacin 500 mg', 'tablet', '500 mg', 'keras', 'tablet', [['Siprofloksasin', 500, 'mg']], [['Strip', 10], ['Box', 100]], 900, 50],
        ['Klaritromisin 500 mg', 'tablet', '500 mg', 'keras', 'tablet', [['Klaritromisin', 500, 'mg']], [['Strip', 10], ['Box', 30]], 3500, 30],
        ['Amlodipine 10 mg', 'tablet', '10 mg', 'keras', 'tablet', [['Amlodipin', 10, 'mg']], [['Strip', 10], ['Box', 100]], 400, 200],
        ['Captopril 25 mg', 'tablet', '25 mg', 'keras', 'tablet', [['Kaptopril', 25, 'mg']], [['Strip', 10], ['Box', 100]], 250, 100],
        ['Simvastatin 20 mg', 'tablet', '20 mg', 'keras', 'tablet', [['Simvastatin', 20, 'mg']], [['Strip', 10], ['Box', 100]], 500, 100],
        ['Metformin 500 mg', 'tablet', '500 mg', 'keras', 'tablet', [['Metformin', 500, 'mg']], [['Strip', 10], ['Box', 100]], 350, 200],
        ['Warfarin 2 mg', 'tablet', '2 mg', 'keras', 'tablet', [['Warfarin', 2, 'mg']], [['Strip', 10], ['Box', 100]], 1500, 30],
        ['Omeprazole 20 mg', 'kapsul', '20 mg', 'keras', 'kapsul', [['Omeprazol', 20, 'mg']], [['Strip', 10], ['Box', 30]], 800, 60],
        ['Cetirizine 10 mg', 'tablet', '10 mg', 'bebas_terbatas', 'tablet', [['Setirizin', 10, 'mg']], [['Strip', 10], ['Box', 50]], 400, 50],
        ['CTM 4 mg', 'tablet', '4 mg', 'bebas_terbatas', 'tablet', [['Klorfeniramin Maleat', 4, 'mg']], [['Strip', 10], ['Box', 100]], 100, 100],
        ['Ambroxol Sirup 15 mg/5 ml', 'sirup', '15 mg/5 ml', 'bebas_terbatas', 'ml', [['Ambroksol', 3, 'mg/ml']], [['Botol', 60]], 150, 600],
        ['Antasida DOEN', 'tablet', '200 mg/200 mg', 'bebas', 'tablet', [['Aluminium Hidroksida', 200, 'mg'], ['Magnesium Hidroksida', 200, 'mg']], [['Strip', 10], ['Box', 100]], 150, 100],
        ['Dexamethasone 0,5 mg', 'tablet', '0,5 mg', 'keras', 'tablet', [['Deksametason', 0.5, 'mg']], [['Strip', 10], ['Box', 100]], 150, 100],
        ['Diazepam 5 mg', 'tablet', '5 mg', 'psikotropika', 'tablet', [['Diazepam', 5, 'mg']], [['Strip', 10], ['Box', 100]], 600, 20],
        ['Codein 10 mg', 'tablet', '10 mg', 'narkotika', 'tablet', [['Kodein', 10, 'mg']], [['Strip', 10], ['Box', 100]], 1200, 20],
    ];

    /** [kode, nama, tarif] */
    private const SERVICES = [
        ['KONS-UMUM', 'Konsultasi dokter umum', 50000],
        ['RAWAT-LUKA', 'Perawatan luka kecil', 35000],
        ['HECTING', 'Jahit luka (per jahitan)', 15000],
        ['NEBU', 'Nebulisasi', 60000],
        ['INJ-IM', 'Injeksi intramuskular', 25000],
        ['GDS', 'Cek gula darah sewaktu', 20000],
        ['AU', 'Cek asam urat', 20000],
    ];

    public function run(DrugService $drugs, TenantContext $context): void
    {
        $tenant = Tenant::where('code', 'KPSS')->firstOrFail();

        $context->runAs($tenant->id, function () use ($drugs) {
            $paymentTypes = PaymentType::pluck('id', 'code');
            $substances = Substance::pluck('id', 'name');
            $poli = Poli::where('code', 'UMUM')->firstOrFail();

            foreach (self::DRUGS as [$name, $form, $strength, $category, $base, $composition, $packs, $price, $min]) {
                $drugs->save(
                    new Drug,
                    ['name' => $name, 'dosage_form' => $form, 'strength' => $strength, 'category' => $category, 'base_unit' => $base, 'min_stock' => $min, 'is_active' => true],
                    array_map(fn ($c) => ['substance_id' => $substances[$c[0]], 'strength_value' => $c[1], 'strength_unit' => $c[2]], $composition),
                    array_merge(
                        [['unit_name' => $base === 'ml' ? 'ml' : ucfirst($base), 'quantity_in_base' => 1]],
                        array_map(fn ($p) => ['unit_name' => $p[0], 'quantity_in_base' => $p[1], 'is_default_sale' => $p[0] === 'Strip' || $p[0] === 'Botol'], $packs),
                    ),
                    [
                        $paymentTypes['UMUM'] => $price,
                        $paymentTypes['ASURANSI'] => (int) round($price * 1.15),
                        // BPJS/Gratis: tagihan pasien Rp0, tetapi nilai obat tetap dicatat untuk valuasi.
                        $paymentTypes['BPJS'] => (int) round($price * 0.8),
                        $paymentTypes['GRATIS'] => (int) round($price * 0.8),
                    ],
                );
            }

            foreach (self::SERVICES as [$code, $name, $price]) {
                $service = MedicalService::create(['poli_id' => $poli->id, 'code' => $code, 'name' => $name]);

                foreach ($paymentTypes as $paymentTypeId) {
                    MedicalServicePrice::create(['medical_service_id' => $service->id, 'payment_type_id' => $paymentTypeId, 'price' => $price]);
                }
            }
        });
    }
}
