<?php

namespace App\Modules\Patient\Enums;

/** Status antrean kunjungan (PRD §6.1) + CANCELLED untuk pasien batal/tidak hadir. */
enum EncounterStatus: string
{
    case Queued = 'QUEUED';
    case NurseAssessed = 'NURSE_ASSESSED';
    case InConsultation = 'IN_CONSULTATION';
    case WaitingPharmacy = 'WAITING_PHARMACY';
    case ReadyBilling = 'READY_BILLING';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Menunggu',
            self::NurseAssessed => 'Diperiksa Perawat',
            self::InConsultation => 'Diperiksa Dokter',
            self::WaitingPharmacy => 'Menunggu Obat',
            self::ReadyBilling => 'Siap Bayar',
            self::Completed => 'Selesai',
            self::Cancelled => 'Batal',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Queued => 'badge-soft-secondary',
            self::NurseAssessed => 'badge-soft-info',
            self::InConsultation => 'badge-soft-primary',
            self::WaitingPharmacy => 'badge-soft-warning',
            self::ReadyBilling => 'badge-soft-warning',
            self::Completed => 'badge-soft-success',
            self::Cancelled => 'badge-soft-danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Queued => 'bi-hourglass-split',
            self::NurseAssessed => 'bi-heart-pulse',
            self::InConsultation => 'bi-clipboard2-pulse',
            self::WaitingPharmacy => 'bi-capsule',
            self::ReadyBilling => 'bi-cash-coin',
            self::Completed => 'bi-check2-circle',
            self::Cancelled => 'bi-x-circle',
        };
    }

    /**
     * Transisi yang sah. Dokter boleh langsung memeriksa tanpa asesmen
     * perawat (faskes kecil), kunjungan tanpa resep langsung ke kasir, dan
     * transaksi yang di-void membuka ulang tagihan (Selesai → Siap Bayar).
     *
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Queued => [self::NurseAssessed, self::InConsultation, self::Cancelled],
            self::NurseAssessed => [self::InConsultation, self::Cancelled],
            self::InConsultation => [self::WaitingPharmacy, self::ReadyBilling],
            self::WaitingPharmacy => [self::ReadyBilling],
            self::ReadyBilling => [self::Completed],
            // Hanya lewat void transaksi kasir (beralasan): tagihan dibuka ulang.
            self::Completed => [self::ReadyBilling],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    /** Kolom waktu yang diisi saat status ini tercapai. */
    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::Queued => null,
            self::NurseAssessed => 'nurse_assessed_at',
            self::InConsultation => 'consultation_started_at',
            self::WaitingPharmacy => 'pharmacy_queued_at',
            self::ReadyBilling => 'billing_ready_at',
            self::Completed => 'completed_at',
            self::Cancelled => 'cancelled_at',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return array_map(fn (self $s) => $s->value, array_filter(self::cases(), fn (self $s) => $s->isOpen()));
    }
}
