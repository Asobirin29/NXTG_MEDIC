<?php

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Enums\Role;
use App\Support\Audit\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

/**
 * User faskes. Sengaja TIDAK memakai BelongsToTenant: guard auth harus bisa
 * memuat user sebelum konteks tenant terisi. Query daftar user wajib memakai
 * scope sameTenantAs().
 */
#[UseFactory(UserFactory::class)]
class User extends Authenticatable
{
    use Auditable, HasFactory, Notifiable;

    protected $fillable = [
        'tenant_id', 'name', 'email', 'role', 'license_number', 'pin', 'is_active', 'password',
    ];

    protected $hidden = ['password', 'pin', 'remember_token'];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin' => 'hashed',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeSameTenantAs(Builder $query, User $user): void
    {
        $query->where('tenant_id', $user->tenant_id);
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function hasPin(): bool
    {
        return filled($this->pin);
    }

    public function checkPin(string $pin): bool
    {
        return $this->hasPin() && Hash::check($pin, $this->pin);
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/', trim(preg_replace('/^(dr|drg|apt|ns|bd)\.?\s+/i', '', $this->name)));

        return strtoupper(mb_substr($words[0] ?? '', 0, 1).mb_substr($words[1] ?? '', 0, 1));
    }
}
