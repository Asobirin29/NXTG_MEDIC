<?php

namespace App\Modules\Patient\Enums;

enum AllergyType: string
{
    case Drug = 'drug';
    case Food = 'food';
    case Environment = 'environment';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Drug => 'Obat (zat aktif)',
            self::Food => 'Makanan',
            self::Environment => 'Lingkungan/cuaca',
            self::Other => 'Lainnya',
        };
    }

    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $t) => $t->value, self::cases()),
            array_map(fn (self $t) => $t->label(), self::cases()),
        );
    }
}
