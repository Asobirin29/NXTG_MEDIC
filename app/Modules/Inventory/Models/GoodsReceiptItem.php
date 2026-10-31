<?php

namespace App\Modules\Inventory\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'goods_receipt_id', 'drug_id', 'batch_id', 'drug_name', 'batch_number', 'expiry_date',
        'unit_name', 'quantity_in_base_per_unit', 'quantity', 'quantity_base', 'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'quantity' => 'integer',
            'quantity_base' => 'integer',
            'quantity_in_base_per_unit' => 'integer',
            'unit_price' => 'integer',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }

    public function subtotal(): int
    {
        return $this->quantity * $this->unit_price;
    }
}
