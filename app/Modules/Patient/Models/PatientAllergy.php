<?php

namespace App\Modules\Patient\Models;

use App\Modules\Cds\Enums\Severity;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Substance;
use App\Modules\Patient\Enums\AllergyType;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientAllergy extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['patient_id', 'type', 'substance_id', 'allergen_name', 'severity', 'reaction', 'recorded_by'];

    protected function casts(): array
    {
        return [
            'type' => AllergyType::class,
            'severity' => Severity::class,
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function substance(): BelongsTo
    {
        return $this->belongsTo(Substance::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function allergenLabel(): string
    {
        return $this->type === AllergyType::Drug
            ? ($this->substance?->name ?? 'Zat aktif #'.$this->substance_id)
            : (string) $this->allergen_name;
    }
}
