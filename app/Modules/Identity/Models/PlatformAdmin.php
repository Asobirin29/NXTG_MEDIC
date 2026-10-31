<?php

namespace App\Modules\Identity\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class PlatformAdmin extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
