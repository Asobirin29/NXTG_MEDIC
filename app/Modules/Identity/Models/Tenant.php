<?php

namespace App\Modules\Identity\Models;

use App\Support\Audit\Auditable;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(TenantFactory::class)]
class Tenant extends Model
{
    use Auditable, HasFactory;

    public const THEMES = [
        'teal' => 'Teal Klinis',
        'indigo' => 'Indigo NexaDash',
    ];

    public const DEFAULT_THEME = 'teal';

    protected $fillable = ['name', 'code', 'type', 'plan', 'status', 'address', 'phone', 'settings'];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function putSetting(string $key, mixed $value): static
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;

        return $this;
    }

    public function theme(): string
    {
        $theme = $this->setting('theme', self::DEFAULT_THEME);

        return array_key_exists($theme, self::THEMES) ? $theme : self::DEFAULT_THEME;
    }
}
