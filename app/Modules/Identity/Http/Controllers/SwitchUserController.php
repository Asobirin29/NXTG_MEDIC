<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Ganti user cepat dengan PIN di komputer bersama (loket/farmasi), PRD §10.5.
 * Hanya bisa berpindah ke user aktif di faskes yang sama.
 */
class SwitchUserController extends Controller
{
    public function show(Request $request): View
    {
        $users = User::sameTenantAs($request->user())
            ->where('is_active', true)
            ->whereNotNull('pin')
            ->whereKeyNot($request->user()->id)
            ->orderBy('name')
            ->get();

        return view('auth.switch-user', compact('users'));
    }

    public function switch(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'pin' => ['required', 'digits_between:4,6'],
        ]);

        $key = 'switch-user:'.$request->user()->tenant_id.'|'.$data['user_id'];

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'pin' => 'Terlalu banyak percobaan PIN. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.',
            ]);
        }

        $target = User::sameTenantAs($request->user())
            ->where('is_active', true)
            ->find($data['user_id']);

        if (! $target || ! $target->checkPin($data['pin'])) {
            RateLimiter::hit($key, 300);

            throw ValidationException::withMessages(['pin' => 'PIN salah.']);
        }

        RateLimiter::clear($key);
        $from = $request->user();

        Auth::guard('web')->login($target);
        $request->session()->regenerate();
        $target->forceFill(['last_login_at' => now()])->saveQuietly();

        $audit->log('switch_user', $target, ['user_id' => $from->id], ['user_id' => $target->id], userId: $target->id);

        return redirect()->route($target->role->homeRoute());
    }
}
