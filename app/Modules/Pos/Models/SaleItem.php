<?php

namespace App\Modules\Pos\Models;

use App\Modules\Emr\Models\EncounterService;
use App\Modules\Emr\Models\PrescriptionItem;
use App\Modules\Inventory\Models\Drug;
use App\Modules\Inventory\Models\DrugPackaging;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'sale_id', 'kind', 'drug_id', 'drug_packaging_id', 'prescription_item_id', 'encounter_service_id',
        'name', 'unit_name', 'quantity_in_base_per_unit', 'quantity', 'quantity_base', 'signa', 'unit_price', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_base' => 'integer',
            'quantity_in_base_per_unit' => 'integer',
            'unit_price' => 'integer',
            'line_total' => 'integer',
            'cost_total' => 'decimal:2',
            'returned_base' => 'integer',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }

    public function packaging(): BelongsTo
    {
        return $this->belongsTo(DrugPackaging::class, 'drug_packaging_id');
    }

    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(PrescriptionItem::class);
    }

    public function encounterService(): BelongsTo
    {
        return $this->belongsTo(EncounterService::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(SaleItemBatch::class);
    }

    public function isDrug(): bool
    {
        return $this->kind === 'drug';
    }

    public function isFromPrescription(): bool
    {
        return $this->prescription_item_id !== null;
    }

    public function returnableBase(): int
    {
        return $this->quantity_base - $this->returned_base;
    }
}
