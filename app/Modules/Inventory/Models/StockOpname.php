<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Identity\Models\User;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Satu sesi hitung fisik. Selisihnya dicatat sebagai mutasi bertipe opname. */
class StockOpname extends Model
{
    use BelongsToTenant;

    protected $fillable = ['number', 'user_id', 'reason', 'batches_counted', 'batches_changed'];

    protected function casts(): array
    {
        return ['batches_counted' => 'integer', 'batches_changed' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }
}
