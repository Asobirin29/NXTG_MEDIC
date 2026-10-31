<?php

namespace Tests;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function makeUser(Role $role, ?Tenant $tenant = null, array $attributes = []): User
    {
        return User::factory()
            ->role($role)
            ->for($tenant ?? Tenant::factory()->create())
            ->create($attributes);
    }
}
