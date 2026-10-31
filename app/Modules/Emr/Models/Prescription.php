<?php

namespace App\Modules\Emr\Models;

use App\Modules\Emr\Enums\PrescriptionStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prescription extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['encounter_id', 'patient_id', 'doctor_id', 'notes'];

    protected function casts(): array
    {
        return [
            'status' => PrescriptionStatus::class,
            'sent_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class)->orderBy('id');
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function isDraft(): bool
    {
        return $this->status === PrescriptionStatus::Draft;
    }
}
