<?php

namespace App\Modules\Emr\Models;

use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Diagnosis ICD-10 per kunjungan (FHIR Condition). */
class EncounterDiagnosis extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['encounter_id', 'icd10_id', 'type', 'note'];

    public function icd10(): BelongsTo
    {
        return $this->belongsTo(Icd10::class);
    }

    public function isPrimary(): bool
    {
        return $this->type === 'primary';
    }
}
