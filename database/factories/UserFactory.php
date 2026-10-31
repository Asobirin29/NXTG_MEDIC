<?php

namespace Database\Factories;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'role' => Role::Kasir,
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function role(Role $role): static
    {
        return $this->state(fn () => [
            'role' => $role,
            'license_number' => $role->requiresLicense() ? fake()->numerify('SIP-######') : null,
        ]);
    }

    public function withPin(string $pin = '1234'): static
    {
        return $this->state(['pin' => $pin]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
