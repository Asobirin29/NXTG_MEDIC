<?php

namespace Tests\Feature\Import;

use App\Modules\Facility\Models\MedicalService;
use App\Modules\Facility\Models\PaymentType;
use App\Modules\Facility\Models\Poli;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Import\Models\Import;
use App\Modules\Import\Support\Spreadsheet;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Drug;
use App\Modules\Inventory\Models\GoodsReceipt;
use App\Modules\Inventory\Models\Substance;
use App\Modules\Inventory\Services\DrugService;
use App\Modules\Patient\Models\Patient;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private User $apoteker;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apoteker = $this->makeUser(Role::Apoteker);
        $this->admin = $this->makeUser(Role::Admin, $this->apoteker->tenant);

        $this->tenant(function () {
            PaymentType::create(['code' => 'UMUM', 'name' => 'Umum', 'patient_pays' => true, 'sort_order' => 1]);
            PaymentType::create(['code' => 'BPJS', 'name' => 'BPJS', 'patient_pays' => false, 'sort_order' => 2]);
            Poli::create(['code' => 'UMUM', 'name' => 'Umum']);
            Substance::create(['name' => 'Parasetamol']);
            Substance::create(['name' => 'Kafein']);
            app(DrugService::class)->save(new Drug,
                ['name' => 'Obat Lama', 'dosage_form' => 'tablet', 'category' => 'bebas', 'base_unit' => 'tablet', 'min_stock' => 0, 'is_active' => true],
                [['substance_id' => Substance::first()->id]],
                [['unit_name' => 'Tablet', 'quantity_in_base' => 1], ['unit_name' => 'Box', 'quantity_in_base' => 100]], []);
        });
    }

    private function tenant(callable $fn): mixed
    {
        return app(TenantContext::class)->runAs($this->apoteker->tenant_id, $fn);
    }

    private function xlsx(array $headers, array $rows, string $name = 'data.xlsx'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        Spreadsheet::writeXlsx($path, $headers, $rows);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function upload(string $type, UploadedFile $file, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->apoteker)->post(route('imports.store'), ['type' => $type, 'file' => $file]);
    }

    private function latest(): Import
    {
        return $this->tenant(fn () => Import::latest('id')->firstOrFail());
    }

    public function test_drug_import_previews_row_errors_then_saves_valid_rows(): void
    {
        $headers = ['Nama*', 'bentuk_sediaan*', 'Golongan*', 'satuan_terkecil*', 'zat_aktif*', 'kemasan', 'kemasan_jual', 'stok_minimum', 'harga_umum', 'harga_bpjs'];
        $this->upload('drugs', $this->xlsx($headers, [
            ['Panadol Extra', 'tablet', 'Obat Bebas', 'tablet', 'Parasetamol 500 mg + Kafein 65 mg', 'Strip=10; Box=100', 'Strip', 50, 'Rp 1.200', 900],
            ['Obat Baru', 'tablet', 'keras', 'tablet', 'Zat Tak Dikenal', '', '', '', '', ''],
            ['obat lama', 'tablet', 'bebas', 'tablet', 'Parasetamol', '', '', '', '', ''],
            ['Sirup X', 'sirup', 'herbal', 'ml', 'Parasetamol', 'Botol=60', 'Dus', 'abc', '', ''],
            ['Panadol Extra', 'tablet', 'bebas', 'tablet', 'Parasetamol', '', '', '', '', ''],
        ]))->assertRedirect();

        $import = $this->latest();
        $this->assertSame(['previewed', 5, 1, 4], [$import->status, $import->total_rows, $import->valid_rows, $import->error_rows]);
        $this->assertStringContainsString('Zat Tak Dikenal', implode(' ', $import->errors[3]));
        $this->assertStringContainsString('sudah ada', implode(' ', $import->errors[4]));
        $this->assertCount(3, $import->errors[5]); // golongan, stok minimum, kemasan jual
        $this->assertStringContainsString('sudah ada', implode(' ', $import->errors[6])); // duplikat di dalam file
        $this->assertSame(1, $this->tenant(fn () => Drug::count()));

        $this->actingAs($this->apoteker)->get(route('imports.show', $import))->assertOk()->assertSee('Impor 1 baris valid');
        $this->actingAs($this->apoteker)->post(route('imports.confirm', $import))->assertSessionHas('success');

        $drug = $this->tenant(fn () => Drug::with(['packagings', 'substances', 'prices'])->where('name', 'Panadol Extra')->sole());
        $this->assertSame(['Box', 'Strip', 'Tablet'], $drug->packagings->sortByDesc('quantity_in_base')->pluck('unit_name')->values()->all());
        $this->assertTrue($drug->packagings->firstWhere('unit_name', 'Strip')->is_default_sale);
        $this->assertSame(['Kafein', 'Parasetamol'], $drug->substances->pluck('name')->sort()->values()->all());
        $this->assertSame([1200, 900], $drug->prices->sortByDesc('price_per_base')->pluck('price_per_base')->values()->all());
        $this->assertSame(['completed', 1], [$import->fresh()->status, $import->fresh()->imported_rows]);
        Storage::disk('local')->assertMissing($import->path);

        $this->actingAs($this->apoteker)->post(route('imports.confirm', $import))->assertSessionHasErrors('import');
    }

    public function test_stock_import_from_semicolon_csv(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'csv').'.csv';
        $ed = today()->addYear()->format('d/m/Y');
        file_put_contents($path, implode("\n", [
            'obat;kemasan;jumlah;no_batch;ed;harga_beli;pemasok',
            "Obat Lama;Box;2;b-01;{$ed};15000;",
            "Obat Lama;;30;B-02;{$ed};;PBF Jaya",
            'Obat Lama;;5;B-03;'.today()->subDay()->format('d/m/Y').';;',
            "Tidak Ada;;5;X;{$ed};;",
            "Obat Lama;;1;B-01;{$ed};;",
            "Obat Lama;Dus;1;B-04;{$ed};;",
        ]));

        $this->upload('stock', new UploadedFile($path, 'stok.csv', null, null, true))->assertRedirect();
        $import = $this->latest();
        $this->assertSame([6, 2, 4], [$import->total_rows, $import->valid_rows, $import->error_rows]);
        $this->assertStringContainsString('kedaluwarsa', implode(' ', $import->errors[4]));
        $this->assertStringContainsString('lebih dari sekali', implode(' ', $import->errors[6]));

        $this->actingAs($this->apoteker)->post(route('imports.confirm', $import));

        $this->assertSame(['B-01' => 200, 'B-02' => 30], $this->tenant(fn () => Batch::orderBy('batch_number')->pluck('qty_on_hand', 'batch_number')->all()));
        $this->assertSame(['PBF Jaya', 'Saldo Awal (Impor)'], $this->tenant(fn () => GoodsReceipt::with('supplier')->get()->pluck('supplier.name')->sort()->values()->all()));
    }

    public function test_patient_import_masks_nik_and_continues_rm_numbers(): void
    {
        $nik = '3273011708850001';
        $this->upload('patients', $this->xlsx(['nama', 'tanggal_lahir', 'jenis_kelamin', 'nik', 'no_rm'], [
            ['Siti Aminah', '17/08/1985', 'Perempuan', $nik, '000120'],
            ['Budi', '1990-02-30', 'X', '123', ''],
            ['Dobel NIK', '01/01/1990', 'L', $nik, ''],
            ['Andi', '05/05/2000', 'L', '', ''],
        ]), $this->admin)->assertRedirect();

        $import = $this->latest();
        $this->assertSame([2, 2], [$import->valid_rows, $import->error_rows]);
        $this->assertCount(3, $import->errors[3]); // tanggal, jenis kelamin, NIK
        $this->assertStringNotContainsString($nik, json_encode($import->preview));
        $this->assertStringContainsString('0001', json_encode($import->preview));

        $this->actingAs($this->admin)->post(route('imports.confirm', $import));

        $patients = $this->tenant(fn () => Patient::orderBy('id')->get());
        $this->assertSame('000120', $patients[0]->medical_record_number);
        $this->assertSame($nik, $patients[0]->nik);
        $next = $this->tenant(fn () => app(\App\Modules\Patient\Services\PatientService::class)->create(['name' => 'Baru', 'birth_date' => '2001-01-01', 'gender' => 'L']));
        $this->assertSame('000121', $next->medical_record_number);
    }

    public function test_service_import_with_prices_per_payment_type(): void
    {
        $this->upload('services', $this->xlsx(['kode', 'nama', 'poli', 'harga_umum', 'harga_bpjs'], [
            ['nebu', 'Nebulisasi', 'UMUM', 60000, 45000],
            ['GDS', 'Gula darah', 'GIGI', 20000, ''],
        ]), $this->admin);
        $this->actingAs($this->admin)->post(route('imports.confirm', $this->latest()));

        $service = $this->tenant(fn () => MedicalService::with('prices')->sole());
        $this->assertSame(['NEBU', [60000, 45000]], [$service->code, $service->prices->sortByDesc('price')->pluck('price')->values()->all()]);
    }

    public function test_missing_columns_rejected_without_leaving_records(): void
    {
        $this->upload('drugs', $this->xlsx(['nama', 'golongan'], [['A', 'bebas']]))->assertSessionHasErrors('file');
        $this->upload('drugs', UploadedFile::fake()->create('obat.pdf', 10))->assertSessionHasErrors('file');

        $this->assertSame(0, $this->tenant(fn () => Import::count()));
    }

    public function test_access_per_type_and_tenant(): void
    {
        $this->actingAs($this->apoteker)->get(route('imports.template', 'drugs'))->assertOk()->assertDownload('templat-impor-drugs.xlsx');
        $this->actingAs($this->apoteker)->get(route('imports.template', 'patients'))->assertForbidden();
        $this->upload('patients', $this->xlsx(['nama'], [['X']]))->assertSessionHasErrors('type');
        $this->actingAs($this->makeUser(Role::Kasir, $this->apoteker->tenant))->get(route('imports.index'))->assertForbidden();

        $this->upload('drugs', $this->xlsx(['nama', 'bentuk_sediaan', 'golongan', 'satuan_terkecil', 'zat_aktif'], [['Z', 'tablet', 'bebas', 'tablet', 'Kafein']]));
        $import = $this->latest();
        $this->actingAs($this->makeUser(Role::Apoteker))->get(route('imports.show', $import))->assertNotFound();

        $this->actingAs($this->apoteker)->get(route('imports.errors', $import))->assertOk();
        $this->actingAs($this->apoteker)->delete(route('imports.destroy', $import))->assertRedirect();
        $this->assertSame(0, $this->tenant(fn () => Import::count()));
    }
}
