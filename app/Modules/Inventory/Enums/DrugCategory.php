<?php

namespace App\Modules\Inventory\Enums;

enum DrugCategory: string
{
    case Bebas = 'bebas';
    case BebasTerbatas = 'bebas_terbatas';
    case Keras = 'keras';
    case Psikotropika = 'psikotropika';
    case Narkotika = 'narkotika';

    public function label(): string
    {
        return match ($this) {
            self::Bebas => 'Obat Bebas',
            self::BebasTerbatas => 'Bebas Terbatas',
            self::Keras => 'Obat Keras',
            self::Psikotropika => 'Psikotropika',
            self::Narkotika => 'Narkotika',
        };
    }

    /** Kelas badge (soft) untuk penanda kategori di daftar obat. */
    public function badge(): string
    {
        return match ($this) {
            self::Bebas => 'badge-soft-success',
            self::BebasTerbatas => 'badge-soft-info',
            self::Keras => 'badge-soft-warning',
            self::Psikotropika, self::Narkotika => 'badge-soft-danger',
        };
    }

    /** Wajib referensi resep bila dijual lewat jalur OTC (PRD §6.5). */
    public function requiresPrescription(): bool
    {
        return in_array($this, [self::Keras, self::Psikotropika, self::Narkotika], true);
    }

    /** Void/retur wajib persetujuan apoteker (PRD §6.5). */
    public function isRestricted(): bool
    {
        return in_array($this, [self::Psikotropika, self::Narkotika], true);
    }

    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $c) => $c->value, self::cases()),
            array_map(fn (self $c) => $c->label(), self::cases()),
        );
    }
}
