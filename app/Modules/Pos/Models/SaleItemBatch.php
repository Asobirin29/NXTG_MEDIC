<?php

namespace App\Modules\Pos\Models;

use App\Modules\Inventory\Models\Batch;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Potongan stok satu item dari satu batch (hasil FEFO saat checkout). */
class SaleItemBatch extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = ['sale_item_id', 'batch_id', 'quantity', 'unit_cost'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'returned' => 'integer', 'unit_cost' => 'decimal:4'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }
}
