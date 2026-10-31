<?php

namespace App\Modules\Patient\Services;

use App\Modules\Patient\Enums\EncounterStatus;
use App\Modules\Patient\Exceptions\InvalidEncounterTransition;
use App\Modules\Patient\Models\Encounter;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya jalan mengubah status kunjungan. Baris dikunci FOR UPDATE
 * supaya dua layar (mis. perawat & loket) tidak menimpa status satu sama lain.
 */
class EncounterStateMachine
{
    public function __construct(private AuditLogger $audit) {}

    public function transition(Encounter $encounter, EncounterStatus $to, ?string $reason = null): Encounter
    {
        if ($to === EncounterStatus::Cancelled && blank($reason)) {
            throw new InvalidEncounterTransition('Alasan pembatalan wajib diisi.');
        }
        if ($encounter->status === EncounterStatus::Completed && blank($reason)) {
            throw new InvalidEncounterTransition('Membuka ulang kunjungan yang sudah selesai wajib disertai alasan.');
        }

        return DB::transaction(function () use ($encounter, $to, $reason) {
            /** @var Encounter $locked */
            $locked = Encounter::whereKey($encounter->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw new InvalidEncounterTransition(
                    "Kunjungan berstatus \"{$from->label()}\" tidak bisa diubah ke \"{$to->label()}\".",
                );
            }

            $locked->status = $to;
            if ($column = $to->timestampColumn()) {
                $locked->{$column} = now();
            }
            if ($to === EncounterStatus::Cancelled) {
                $locked->cancel_reason = $reason;
            }

            // Simpan tanpa event Auditable umum; transisi dicatat sebagai satu entri yang jelas.
            $locked->saveQuietly();
            $this->audit->log('encounter_status', $locked, ['status' => $from->value], ['status' => $to->value], $reason);

            $encounter->setRawAttributes($locked->getAttributes(), true);

            return $encounter;
        });
    }
}
