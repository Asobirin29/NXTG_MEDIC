<?php

namespace App\Modules\Emr\Models;

use App\Modules\Emr\Exceptions\MedicalRecordLocked;
use App\Modules\Identity\Models\User;
use App\Modules\Patient\Models\Encounter;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** CPPT dokter (SOAP). Setelah dikunci tidak bisa diubah/dihapus; koreksi lewat addendum (PRD §6.2). */
class ClinicalNote extends Model
{
    use Auditable, BelongsToTenant;

    public const SECTIONS = [
        'subjective' => 'S — Subjective',
        'objective' => 'O — Objective',
        'assessment' => 'A — Assessment',
        'plan' => 'P — Plan',
    ];

    protected $fillable = ['encounter_id', 'doctor_id', 'subjective', 'objective', 'assessment', 'plan'];

    protected function casts(): array
    {
        return ['locked_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $note) {
            if ($note->getOriginal('status') === 'locked') {
                throw new MedicalRecordLocked;
            }
        });
        static::deleting(function (self $note) {
            if ($note->getOriginal('status') === 'locked') {
                throw new MedicalRecordLocked;
            }
        });
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function addenda(): HasMany
    {
        return $this->hasMany(ClinicalNoteAddendum::class)->orderBy('created_at');
    }
}
