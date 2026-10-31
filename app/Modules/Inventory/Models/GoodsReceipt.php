<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Identity\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Penerimaan barang dari pemasok. Final setelah disimpan; koreksi lewat penyesuaian stok. */
class GoodsReceipt extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['number', 'supplier_id', 'invoice_number', 'received_at', 'received_by', 'notes', 'total_cost'];

    protected function casts(): array
    {
        return ['received_at' => 'date', 'total_cost' => 'integer'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }
}
