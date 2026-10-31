<?php

namespace App\Modules\Emr\Models;

use App\Modules\Facility\Models\MedicalService;
use App\Modules\Identity\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tindakan yang diberikan pada kunjungan; unit_price = tarif saat itu sesuai jenis bayar. */
class EncounterService extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['encounter_id', 'medical_service_id', 'quantity', 'unit_price', 'note', 'performed_by'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price' => 'integer'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(MedicalService::class, 'medical_service_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function subtotal(): int
    {
        return $this->quantity * $this->unit_price;
    }
}
