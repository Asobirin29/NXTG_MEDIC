<?php

namespace App\Modules\Identity\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Kasir = 'kasir';
    case Perawat = 'perawat';
    case Dokter = 'dokter';
    case Apoteker = 'apoteker';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin Faskes',
            self::Kasir => 'Petugas Loket / Kasir',
            self::Perawat => 'Perawat / Bidan',
            self::Dokter => 'Dokter',
            self::Apoteker => 'Apoteker',
        };
    }

    /** Halaman awal sesuai role (PRD §11.1). */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Kasir => 'registration.index',
            self::Perawat, self::Dokter => 'emr.index',
            self::Admin, self::Apoteker => 'dashboard',
        };
    }

    /** Role tenaga medis wajib punya nomor SIP/STR. */
    public function requiresLicense(): bool
    {
        return in_array($this, [self::Dokter, self::Perawat, self::Apoteker], true);
    }

    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $r) => $r->value, self::cases()),
            array_map(fn (self $r) => $r->label(), self::cases()),
        );
    }
}
