<?php

use App\Modules\Cds\Http\Controllers\InteractionRuleController;
use App\Modules\Emr\Http\Controllers\ConsultationController;
use App\Modules\Emr\Http\Controllers\EmrQueueController;
use App\Modules\Emr\Http\Controllers\EncounterItemController;
use App\Modules\Emr\Http\Controllers\Icd10SearchController;
use App\Modules\Emr\Http\Controllers\NurseAssessmentController;
use App\Modules\Emr\Http\Controllers\PrescriptionController;
use App\Modules\Emr\Http\Controllers\SoapTemplateController;
use App\Modules\Facility\Http\Controllers\MedicalServiceController;
use App\Modules\Facility\Http\Controllers\PaymentTypeController;
use App\Modules\Facility\Http\Controllers\PoliController;
use App\Modules\Identity\Http\Controllers\AppearanceController;
use App\Modules\Identity\Http\Controllers\AuditLogController;
use App\Modules\Identity\Http\Controllers\DashboardController;
use App\Modules\Identity\Http\Controllers\LoginController;
use App\Modules\Identity\Http\Controllers\Platform\PlatformAuthController;
use App\Modules\Identity\Http\Controllers\Platform\TenantController;
use App\Modules\Identity\Http\Controllers\SwitchUserController;
use App\Modules\Identity\Http\Controllers\UserController;
use App\Modules\Import\Http\Controllers\ImportController;
use App\Modules\Inventory\Http\Controllers\DrugController;
use App\Modules\Inventory\Http\Controllers\DrugSearchController;
use App\Modules\Inventory\Http\Controllers\GoodsReceiptController;
use App\Modules\Inventory\Http\Controllers\StockController;
use App\Modules\Inventory\Http\Controllers\StockOpnameController;
use App\Modules\Inventory\Http\Controllers\SubstanceController;
use App\Modules\Inventory\Http\Controllers\SupplierController;
use App\Modules\Patient\Http\Controllers\PatientAllergyController;
use App\Modules\Patient\Http\Controllers\PatientController;
use App\Modules\Patient\Http\Controllers\RegistrationController;
use App\Modules\Pos\Http\Controllers\PosController;
use App\Modules\Pos\Http\Controllers\SaleController;
use App\Modules\Reporting\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

/* ---------------- Aplikasi faskes ---------------- */

Route::middleware('guest:web')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::middleware(['auth:web', 'tenant'])->group(function () {
    Route::get('/', fn () => redirect()->route(auth()->user()->role->homeRoute()))->name('home');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/ganti-pengguna', [SwitchUserController::class, 'show'])->name('switch-user');
    Route::post('/ganti-pengguna', [SwitchUserController::class, 'switch'])->name('switch-user.attempt');

    Route::get('/beranda', DashboardController::class)->name('dashboard')->middleware('can:dashboard.view');

    Route::prefix('pengaturan')->name('settings.')->middleware('can:settings.manage')->group(function () {
        Route::resource('pengguna', UserController::class)
            ->except(['show', 'destroy'])
            ->parameters(['pengguna' => 'user'])
            ->names('users');
        Route::get('/tampilan', [AppearanceController::class, 'edit'])->name('appearance');
        Route::put('/tampilan', [AppearanceController::class, 'update'])->name('appearance.update');
        Route::resource('poli', PoliController::class)->except(['show', 'destroy'])->names('polis');
        Route::resource('jenis-pembayaran', PaymentTypeController::class)->except(['show', 'destroy'])
            ->parameters(['jenis-pembayaran' => 'paymentType'])->names('payment-types');
        Route::resource('tindakan', MedicalServiceController::class)->except(['show', 'destroy'])
            ->parameters(['tindakan' => 'service'])->names('services');
    });

    // Pasien & alergi.
    Route::middleware('can:patients.manage')->group(function () {
        Route::get('/pasien/baru', [PatientController::class, 'create'])->name('patients.create');
        Route::post('/pasien', [PatientController::class, 'store'])->name('patients.store');
        Route::get('/pasien/{patient}/ubah', [PatientController::class, 'edit'])->name('patients.edit');
        Route::put('/pasien/{patient}', [PatientController::class, 'update'])->name('patients.update');
    });
    Route::middleware('can:patients.view')->group(function () {
        Route::get('/pasien', [PatientController::class, 'index'])->name('patients.index');
        Route::get('/pasien/{patient}', [PatientController::class, 'show'])->name('patients.show');
    });
    Route::middleware('can:allergies.manage')->group(function () {
        Route::post('/pasien/{patient}/alergi', [PatientAllergyController::class, 'store'])->name('patients.allergies.store');
        Route::delete('/pasien/{patient}/alergi/{allergy}', [PatientAllergyController::class, 'destroy'])->name('patients.allergies.destroy');
    });

    // Loket pendaftaran & antrean.
    Route::middleware('can:registration.access')->prefix('antrean')->name('registration.')->group(function () {
        Route::get('/', [RegistrationController::class, 'index'])->name('index');
        Route::get('/daftar', [RegistrationController::class, 'create'])->name('create');
        Route::post('/daftar', [RegistrationController::class, 'store'])->name('store');
        Route::post('/{encounter}/batal', [RegistrationController::class, 'cancel'])->name('cancel');
    });

    // Ruang periksa (RME).
    Route::prefix('ruang-periksa')->name('emr.')->group(function () {
        Route::get('/', [EmrQueueController::class, 'index'])->name('index')->middleware('can:emr.access');

        Route::middleware('can:emr.soap')->group(function () {
            Route::get('/template', [SoapTemplateController::class, 'index'])->name('templates.index');
            Route::post('/template', [SoapTemplateController::class, 'store'])->name('templates.store');
            Route::delete('/template/{template}', [SoapTemplateController::class, 'destroy'])->name('templates.destroy');
        });

        Route::middleware('can:emr.vitals')->group(function () {
            Route::get('/{encounter}/asesmen', [NurseAssessmentController::class, 'show'])->name('assessment');
            Route::post('/{encounter}/asesmen', [NurseAssessmentController::class, 'store'])->name('assessment.store');
        });

        Route::middleware('can:emr.soap')->group(function () {
            Route::get('/{encounter}', [ConsultationController::class, 'show'])->name('consultation');
            Route::post('/{encounter}/mulai', [ConsultationController::class, 'start'])->name('start');
            Route::put('/{encounter}/soap', [ConsultationController::class, 'saveDraft'])->name('soap');
            // PUT: dikirim dari form SOAP yang sama (method spoofing) lewat tombol "Selesai".
            Route::put('/{encounter}/selesai', [ConsultationController::class, 'finish'])->name('finish');
            Route::post('/{encounter}/addendum', [ConsultationController::class, 'addendum'])->name('addendum');
            Route::post('/{encounter}/diagnosis', [EncounterItemController::class, 'storeDiagnosis'])->name('diagnoses.store');
            Route::delete('/{encounter}/diagnosis/{diagnosis}', [EncounterItemController::class, 'destroyDiagnosis'])->name('diagnoses.destroy');
            Route::post('/{encounter}/tindakan', [EncounterItemController::class, 'storeService'])->name('services.store');
            Route::delete('/{encounter}/tindakan/{service}', [EncounterItemController::class, 'destroyService'])->name('services.destroy');
            Route::post('/{encounter}/resep', [PrescriptionController::class, 'store'])->name('prescription.store');
            Route::post('/{encounter}/resep/salin', [PrescriptionController::class, 'copyLast'])->name('prescription.copy');
            Route::delete('/{encounter}/resep/{item}', [PrescriptionController::class, 'destroy'])->name('prescription.destroy');
        });
    });

    // Referensi yang dipakai lintas modul (pemilih zat aktif, diagnosis).
    Route::get('/cari/zat-aktif', [SubstanceController::class, 'search'])->name('substances.search');
    Route::get('/cari/icd10', Icd10SearchController::class)->name('icd10.search');
    Route::get('/cari/obat', DrugSearchController::class)->name('drugs.search')->middleware('can:drugs.search');

    Route::middleware('can:inventory.manage')->group(function () {
        Route::resource('obat', DrugController::class)->except(['show', 'destroy'])->parameters(['obat' => 'drug'])->names('drugs');
        Route::get('/zat-aktif', [SubstanceController::class, 'index'])->name('substances.index');
        Route::post('/zat-aktif', [SubstanceController::class, 'store'])->name('substances.store');
        Route::put('/zat-aktif/{substance}', [SubstanceController::class, 'update'])->name('substances.update');

        Route::get('/pemasok', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('/pemasok', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::put('/pemasok/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');

        Route::get('/penerimaan', [GoodsReceiptController::class, 'index'])->name('receipts.index');
        Route::get('/penerimaan/baru', [GoodsReceiptController::class, 'create'])->name('receipts.create');
        Route::post('/penerimaan', [GoodsReceiptController::class, 'store'])->name('receipts.store');
        Route::get('/penerimaan/{receipt}', [GoodsReceiptController::class, 'show'])->name('receipts.show');

        Route::post('/stok/batch/{batch}/penyesuaian', [StockController::class, 'adjust'])->name('stock.adjust');
        Route::get('/stok/opname', [StockOpnameController::class, 'create'])->name('stock.opname');
        Route::post('/stok/opname', [StockOpnameController::class, 'store'])->name('stock.opname.store');
    });

    Route::middleware('can:inventory.view')->group(function () {
        Route::get('/stok', [StockController::class, 'index'])->name('stock.index');
        Route::get('/stok/obat/{drug}', [StockController::class, 'show'])->name('stock.show');
    });

    Route::middleware('can:interactions.manage')->group(function () {
        Route::post('/interaksi/sinkron-template', [InteractionRuleController::class, 'syncTemplates'])->name('interactions.sync');
        Route::resource('interaksi', InteractionRuleController::class)->except(['show'])
            ->parameters(['interaksi' => 'interaction'])->names('interactions');
    });

    // Farmasi & Kasir (POS).
    Route::prefix('farmasi')->name('pos.')->middleware('can:pos.access')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::get('/cari-pasien', [PosController::class, 'patients'])->name('patients');
        Route::post('/kunjungan/{encounter}', [PosController::class, 'openEncounter'])->name('open-encounter');
        Route::post('/otc', [PosController::class, 'openOtc'])->name('open-otc');

        Route::get('/keranjang/{sale}', [PosController::class, 'show'])->name('cart');
        Route::put('/keranjang/{sale}', [PosController::class, 'update'])->name('cart.update');
        Route::delete('/keranjang/{sale}', [PosController::class, 'discard'])->name('cart.discard');
        Route::post('/keranjang/{sale}/item', [PosController::class, 'addItem'])->name('cart.items.store');
        Route::post('/keranjang/{sale}/scan', [PosController::class, 'scan'])->name('cart.scan');
        Route::delete('/keranjang/{sale}/item/{item}', [PosController::class, 'removeItem'])->name('cart.items.destroy');
        Route::post('/keranjang/{sale}/verifikasi', [PosController::class, 'verify'])->name('cart.verify')->middleware('can:pharmacy.verify');
        Route::post('/keranjang/{sale}/bayar', [PosController::class, 'checkout'])->name('cart.checkout');
        Route::get('/keranjang/{sale}/etiket', [SaleController::class, 'labels'])->name('labels');

        Route::get('/transaksi', [SaleController::class, 'index'])->name('sales.index');
        Route::get('/transaksi/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::get('/transaksi/{sale}/struk', [SaleController::class, 'receipt'])->name('sales.receipt');
        Route::post('/transaksi/{sale}/void', [SaleController::class, 'void'])->name('sales.void')->middleware('can:pos.void');
        Route::post('/transaksi/{sale}/retur', [SaleController::class, 'storeReturn'])->name('sales.return')->middleware('can:pos.return.request');
    });

    // Impor data onboarding (izin per jenis dicek di controller).
    Route::prefix('impor')->name('imports.')->middleware('can:imports.access')->group(function () {
        Route::get('/', [ImportController::class, 'index'])->name('index');
        Route::post('/', [ImportController::class, 'store'])->name('store');
        Route::get('/templat/{type}', [ImportController::class, 'template'])->name('template');
        Route::get('/{import}', [ImportController::class, 'show'])->name('show');
        Route::post('/{import}/proses', [ImportController::class, 'confirm'])->name('confirm');
        Route::delete('/{import}', [ImportController::class, 'destroy'])->name('destroy');
        Route::get('/{import}/kesalahan', [ImportController::class, 'errors'])->name('errors');
    });

    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit.index')->middleware('can:audit.view');

    Route::middleware('can:reports.view')->group(function () {
        Route::get('/laporan', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/laporan/{report}', [ReportController::class, 'show'])->name('reports.show');
    });
});

/* ---------------- Panel Admin Platform ---------------- */

Route::prefix('platform')->name('platform.')->group(function () {
    Route::middleware('guest:platform')->group(function () {
        Route::get('/login', [PlatformAuthController::class, 'show'])->name('login');
        Route::post('/login', [PlatformAuthController::class, 'login'])->name('login.attempt');
    });

    Route::middleware('auth:platform')->group(function () {
        Route::get('/', fn () => redirect()->route('platform.tenants.index'));
        Route::post('/logout', [PlatformAuthController::class, 'logout'])->name('logout');
        Route::resource('faskes', TenantController::class)
            ->except(['show', 'destroy'])
            ->parameters(['faskes' => 'tenant'])
            ->names('tenants');
    });
});
