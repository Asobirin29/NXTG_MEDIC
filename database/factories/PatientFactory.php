<?php

namespace Database\Factories;

use App\Modules\Identity\Models\Tenant;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'medical_record_number' => fake()->unique()->numerify('9#####'),
            'name' => fake()->name(),
            'birth_date' => fake()->dateTimeBetween('-70 years', '-1 year'),
            'gender' => fake()->randomElement(['L', 'P']),
            'nik' => null,
            'phone' => fake()->numerify('08##########'),
            'is_active' => true,
        ];
    }
}
