<?php

namespace App\Modules\Emr\Enums;

/** Status resep (PRD §10.3): Draft → Menunggu Verifikasi → Disiapkan → Diserahkan. */
enum PrescriptionStatus: string
{
    case Draft = 'draft';
    case PendingVerification = 'pending_verification';
    case Prepared = 'prepared';
    case Dispensed = 'dispensed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::PendingVerification => 'Menunggu Verifikasi',
            self::Prepared => 'Disiapkan',
            self::Dispensed => 'Diserahkan',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'badge-soft-secondary',
            self::PendingVerification => 'badge-soft-warning',
            self::Prepared => 'badge-soft-info',
            self::Dispensed => 'badge-soft-success',
            self::Cancelled => 'badge-soft-danger',
        };
    }
}
