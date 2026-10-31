<?php

use App\Http\Middleware\SetTenantContext;
use App\Modules\Emr\Exceptions\EmrActionNotAllowed;
use App\Modules\Emr\Exceptions\MedicalRecordLocked;
use App\Modules\Inventory\Exceptions\InsufficientStock;
use App\Modules\Inventory\Exceptions\StockChanged;
use App\Modules\Patient\Exceptions\InvalidEncounterTransition;
use App\Modules\Pos\Exceptions\PosActionNotAllowed;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => SetTenantContext::class,
        ]);

        // Konteks tenant harus terisi sebelum route-model binding, supaya
        // {poli}/{drug} milik faskes lain tidak ikut ter-resolve (404).
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: SetTenantContext::class);

        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('platform', 'platform/*')
            ? route('platform.login')
            : route('login'));

        $middleware->redirectUsersTo(fn (Request $request) => $request->is('platform', 'platform/*')
            ? route('platform.tenants.index')
            : route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Pelanggaran aturan alur klinis & stok (status kunjungan, RME terkunci, stok kurang) ditampilkan
        // sebagai pesan biasa, bukan halaman error 500.
        $exceptions->render(function (DomainException $e, Request $request) {
            $handled = [EmrActionNotAllowed::class, MedicalRecordLocked::class, InvalidEncounterTransition::class, InsufficientStock::class, StockChanged::class, PosActionNotAllowed::class];
            if (! in_array($e::class, $handled, true)) {
                return null;
            }

            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withInput()->with('error', $e->getMessage());
        });
    })->create();
