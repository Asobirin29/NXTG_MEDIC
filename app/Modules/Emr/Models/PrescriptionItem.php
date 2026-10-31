<?php

namespace App\Modules\Emr\Models;

use App\Modules\Inventory\Models\Drug;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionItem extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = [
        'prescription_id', 'drug_id', 'drug_packaging_id', 'drug_name', 'unit_name',
        'quantity_in_base_per_unit', 'quantity', 'quantity_base', 'signa', 'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_base' => 'integer',
            'quantity_in_base_per_unit' => 'integer',
        ];
    }

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }
}
