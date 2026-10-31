<?php

namespace App\Modules\Cds\Enums;

/** Tingkat keparahan peringatan klinis (PRD §6.3, §11.1). */
enum Severity: string
{
    case Minor = 'minor';
    case Moderate = 'moderate';
    case Major = 'major';

    public function label(): string
    {
        return match ($this) {
            self::Minor => 'Ringan',
            self::Moderate => 'Sedang',
            self::Major => 'Berat',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Minor => 'bi-info-circle',
            self::Moderate => 'bi-exclamation-triangle-fill',
            self::Major => 'bi-x-octagon-fill',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Minor => 'badge-soft-secondary',
            self::Moderate => 'badge-soft-warning',
            self::Major => 'badge-soft-danger',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Minor => 1,
            self::Moderate => 2,
            self::Major => 3,
        };
    }

    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $s) => $s->value, self::cases()),
            array_map(fn (self $s) => $s->label(), self::cases()),
        );
    }
}
