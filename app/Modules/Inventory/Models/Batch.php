<?php

namespace App\Modules\Inventory\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Saldo satu batch obat (satuan terkecil). qty_on_hand hanya diubah lewat
 * StockLedger agar selalu sejalan dengan buku besar stock_movements; karena
 * itu batch tidak memakai Auditable (jejaknya ada di buku besar).
 */
class Batch extends Model
{
    use BelongsToTenant;

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_QUARANTINE = 'quarantine';

    /** Batas "mendekati kedaluwarsa" (PRD §6.4). */
    public const NEAR_EXPIRY_DAYS = 90;

    protected $fillable = ['drug_id', 'batch_number', 'expiry_date', 'purchase_price_per_base', 'supplier_id', 'status', 'qty_on_hand'];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'qty_on_hand' => 'integer',
            'purchase_price_per_base' => 'decimal:4',
        ];
    }

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Kedaluwarsa = tanggal ED sudah tiba. Obat ber-ED hari ini ikut diblokir
     * karena pasien masih akan memakainya beberapa hari ke depan.
     */
    public function isExpired(?Carbon $today = null): bool
    {
        return $this->expiry_date->lte(($today ?? today())->copy()->startOfDay());
    }

    public function isNearExpiry(?Carbon $today = null): bool
    {
        $today = ($today ?? today())->copy()->startOfDay();

        return ! $this->isExpired($today) && $this->expiry_date->lte($today->copy()->addDays(self::NEAR_EXPIRY_DAYS));
    }

    public function daysToExpiry(): int
    {
        return (int) today()->diffInDays($this->expiry_date, false);
    }

    public function isQuarantined(): bool
    {
        return $this->status === self::STATUS_QUARANTINE;
    }

    /** Batch yang boleh dikeluarkan: layak jual, ada saldo, belum kedaluwarsa. */
    public function scopeDispensable(Builder $query): void
    {
        $query->where('status', self::STATUS_AVAILABLE)
            ->where('qty_on_hand', '>', 0)
            ->whereDate('expiry_date', '>', today());
    }

    /** Urutan FEFO: kedaluwarsa terdekat dulu, lalu yang diterima lebih dulu. */
    public function scopeFefo(Builder $query): void
    {
        $query->orderBy('expiry_date')->orderBy('id');
    }
}
