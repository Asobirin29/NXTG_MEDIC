<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Isi TenantContext dari user login, dan keluarkan user bila akunnya
 * dinonaktifkan atau faskesnya ditangguhkan platform.
 */
class SetTenantContext
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = $user?->tenant;

        if (! $user || ! $tenant || ! $user->is_active || ! $tenant->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = $tenant && ! $tenant->isActive()
                ? 'Akses faskes ini sedang ditangguhkan. Hubungi pengelola layanan.'
                : 'Akun Anda tidak aktif.';

            return redirect()->route('login')->withErrors(['email' => $message]);
        }

        $this->context->set($tenant->id);

        return $next($request);
    }
}
