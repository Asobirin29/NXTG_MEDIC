<?php

namespace App\Modules\Emr\Services;

use App\Modules\Emr\Exceptions\EmrActionNotAllowed;
use App\Modules\Emr\Exceptions\MedicalRecordLocked;
use App\Modules\Emr\Models\ClinicalNote;
use App\Modules\Patient\Enums\EncounterStatus;
use App\Modules\Patient\Models\Encounter;

/** Aturan tunggal "rekam medis kunjungan ini masih boleh diubah?" — dipakai SOAP, diagnosis, tindakan, resep. */
class EncounterGuard
{
    public function editableNote(Encounter $encounter): ClinicalNote
    {
        $note = $encounter->clinicalNote()->first();

        if ($note?->isLocked()) {
            throw new MedicalRecordLocked;
        }
        if ($encounter->status !== EncounterStatus::InConsultation || ! $note) {
            throw new EmrActionNotAllowed('Mulai pemeriksaan terlebih dahulu.');
        }

        return $note;
    }
}
