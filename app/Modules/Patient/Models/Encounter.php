<?php

namespace App\Modules\Patient\Models;

use App\Modules\Emr\Models\ClinicalNote;
use App\Modules\Emr\Models\EncounterDiagnosis;
use App\Modules\Emr\Models\EncounterService;
use App\Modules\Emr\Models\Prescription;
use App\Modules\Emr\Models\VitalSign;
use App\Modules\Facility\Models\PaymentType;
use App\Modules\Facility\Models\Poli;
use App\Modules\Identity\Models\User;
use App\Modules\Patient\Enums\EncounterStatus;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Kunjungan pasien ke poli (FHIR Encounter). Status hanya diubah lewat EncounterStateMachine. */
class Encounter extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = [
        'patient_id', 'poli_id', 'doctor_id', 'payment_type_id', 'queue_date', 'queue_number',
        'status', 'registered_by', 'registered_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EncounterStatus::class,
            'queue_date' => 'date',
            'registered_at' => 'datetime',
            'nurse_assessed_at' => 'datetime',
            'consultation_started_at' => 'datetime',
            'pharmacy_queued_at' => 'datetime',
            'billing_ready_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function poli(): BelongsTo
    {
        return $this->belongsTo(Poli::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function vitalSigns(): HasMany
    {
        return $this->hasMany(VitalSign::class)->orderByDesc('recorded_at');
    }

    public function latestVital(): HasOne
    {
        return $this->hasOne(VitalSign::class)->latestOfMany('recorded_at');
    }

    public function clinicalNote(): HasOne
    {
        return $this->hasOne(ClinicalNote::class);
    }

    public function prescription(): HasOne
    {
        return $this->hasOne(Prescription::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(EncounterDiagnosis::class)->orderByRaw("FIELD(type, 'primary', 'secondary')")->orderBy('id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(EncounterService::class)->orderBy('id');
    }

    /** "UMUM-007". */
    public function queueLabel(): string
    {
        return ($this->poli?->code ?? 'Q').'-'.str_pad((string) $this->queue_number, 3, '0', STR_PAD_LEFT);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', EncounterStatus::openValues());
    }

    public function scopeOnDate(Builder $query, $date): void
    {
        $query->whereDate('queue_date', $date);
    }
}
