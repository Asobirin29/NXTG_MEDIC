<?php

namespace App\Modules\Pos\Services;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Persetujuan apoteker di tempat (PRD §6.5): apoteker memilih namanya dan
 * mengetik PIN di layar kasir. Dibatasi 5 percobaan per 5 menit.
 */
class PharmacistApproval
{
    public function verify(User $actor, ?int $pharmacistId, ?string $pin, string $field = 'approver_pin'): User
    {
        $key = 'pharmacist-approval:'.$actor->tenant_id.'|'.$pharmacistId;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([$field => 'Terlalu banyak percobaan PIN. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.']);
        }

        $pharmacist = $pharmacistId
            ? User::sameTenantAs($actor)->where('is_active', true)->where('role', Role::Apoteker)->find($pharmacistId)
            : null;

        if (! $pharmacist || blank($pin) || ! $pharmacist->checkPin($pin)) {
            RateLimiter::hit($key, 300);

            throw ValidationException::withMessages([$field => 'Persetujuan apoteker gagal: pilih apoteker dan masukkan PIN yang benar.']);
        }

        RateLimiter::clear($key);

        return $pharmacist;
    }

    /** Apoteker yang sedang login dianggap menyetujui sendiri; peran lain wajib PIN apoteker. */
    public function resolve(User $actor, ?int $pharmacistId, ?string $pin, string $field = 'approver_pin'): User
    {
        return $actor->hasRole(Role::Apoteker) ? $actor : $this->verify($actor, $pharmacistId, $pin, $field);
    }
}
