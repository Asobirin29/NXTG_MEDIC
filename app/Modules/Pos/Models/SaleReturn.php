<?php

namespace App\Modules\Pos\Models;

use App\Modules\Identity\Models\User;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Retur obat yang sudah diserahkan; disetujui apoteker dengan PIN (PRD §6.5). */
class SaleReturn extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['number', 'sale_id', 'user_id', 'approved_by', 'reason', 'refund_amount'];

    protected function casts(): array
    {
        return ['refund_amount' => 'integer'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
