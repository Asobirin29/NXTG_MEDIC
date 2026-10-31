<?php

namespace App\Modules\Pos\Models;

use App\Modules\Inventory\Models\Batch;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnItem extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = ['sale_return_id', 'sale_item_id', 'sale_item_batch_id', 'batch_id', 'quantity', 'condition', 'refund'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'refund' => 'integer'];
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
