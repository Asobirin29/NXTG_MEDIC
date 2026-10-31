<?php

namespace Database\Factories;

use App\Modules\Identity\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'name' => 'Klinik '.fake()->unique()->lastName(),
            'code' => strtoupper(fake()->unique()->bothify('KL###??')),
            'type' => 'klinik',
            'plan' => 'basic',
            'status' => 'active',
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'settings' => ['theme' => Tenant::DEFAULT_THEME],
        ];
    }

    public function suspended(): static
    {
        return $this->state(['status' => 'suspended']);
    }
}
