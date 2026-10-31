<?php

namespace Tests\Unit;

use App\Modules\Patient\Enums\EncounterStatus as S;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EncounterStatusTest extends TestCase
{
    /** Matriks lengkap: setiap pasangan (dari, ke) yang sah. */
    private const ALLOWED = [
        'QUEUED' => ['NURSE_ASSESSED', 'IN_CONSULTATION', 'CANCELLED'],
        'NURSE_ASSESSED' => ['IN_CONSULTATION', 'CANCELLED'],
        'IN_CONSULTATION' => ['WAITING_PHARMACY', 'READY_BILLING'],
        'WAITING_PHARMACY' => ['READY_BILLING'],
        'READY_BILLING' => ['COMPLETED'],
        'COMPLETED' => ['READY_BILLING'],
        'CANCELLED' => [],
    ];

    public static function pairs(): array
    {
        $cases = [];
        foreach (S::cases() as $from) {
            foreach (S::cases() as $to) {
                $cases["{$from->value} → {$to->value}"] = [$from, $to, in_array($to->value, self::ALLOWED[$from->value], true)];
            }
        }

        return $cases;
    }

    #[DataProvider('pairs')]
    public function test_transition_matrix(S $from, S $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }

    public function test_happy_path_follows_prd_order(): void
    {
        $path = [S::Queued, S::NurseAssessed, S::InConsultation, S::WaitingPharmacy, S::ReadyBilling, S::Completed];

        for ($i = 0; $i < count($path) - 1; $i++) {
            $this->assertTrue($path[$i]->canTransitionTo($path[$i + 1]), "{$path[$i]->value} → {$path[$i + 1]->value}");
        }
    }

    public function test_terminal_states_are_closed(): void
    {
        $this->assertFalse(S::Completed->isOpen());
        $this->assertFalse(S::Cancelled->isOpen());
        $this->assertSame(['QUEUED', 'NURSE_ASSESSED', 'IN_CONSULTATION', 'WAITING_PHARMACY', 'READY_BILLING'], S::openValues());
    }

    public function test_every_non_initial_status_has_timestamp_column(): void
    {
        foreach (S::cases() as $status) {
            $status === S::Queued
                ? $this->assertNull($status->timestampColumn())
                : $this->assertNotNull($status->timestampColumn(), $status->value);
        }
    }
}
