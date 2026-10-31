<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Baris buku besar stok. Append-only (PRD §6.4): koreksi dibuat sebagai
 * mutasi baru (penyesuaian/opname), tidak pernah dengan mengubah baris lama.
 * Database juga menolak UPDATE/DELETE lewat trigger.
 */
class StockMovement extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = ['batch_id', 'drug_id', 'type', 'quantity', 'balance_after', 'reason', 'reference_type', 'reference_id', 'user_id'];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Mutasi stok tidak bisa diubah. Buat penyesuaian stok.'));
        static::deleting(fn () => throw new LogicException('Mutasi stok tidak bisa dihapus. Buat penyesuaian stok.'));
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
