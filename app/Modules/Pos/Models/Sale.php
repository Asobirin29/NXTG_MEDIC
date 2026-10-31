<?php

namespace App\Modules\Pos\Models;

use App\Modules\Emr\Models\Prescription;
use App\Modules\Facility\Models\PaymentType;
use App\Modules\Identity\Models\User;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Keranjang (open) yang menjadi transaksi (completed) saat dibayar.
 * Harga & batch dibekukan di sale_items / sale_item_batches pada checkout.
 */
class Sale extends Model
{
    use Auditable, BelongsToTenant;

    public const OPEN = 'open';

    public const COMPLETED = 'completed';

    public const VOIDED = 'voided';

    protected $attributes = ['status' => self::OPEN];

    protected $fillable = ['type', 'encounter_id', 'prescription_id', 'patient_id', 'payment_type_id', 'external_rx_ref', 'label', 'created_by'];

    protected function casts(): array
    {
        return [
            'total_value' => 'integer',
            'amount_due' => 'integer',
            'paid_amount' => 'integer',
            'change_amount' => 'integer',
            'cost_total' => 'decimal:2',
            'completed_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class)->orderBy('kind')->orderBy('id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::COMPLETED;
    }

    public function isEncounter(): bool
    {
        return $this->type === 'encounter';
    }

    public function scopeOpen(Builder $query): void
    {
        $query->where('status', self::OPEN);
    }

    public function statusLabel(): string
    {
        return ['open' => 'Keranjang', 'completed' => 'Lunas', 'voided' => 'Void'][$this->status];
    }

    public function statusBadge(): string
    {
        return ['open' => 'badge-soft-secondary', 'completed' => 'badge-soft-success', 'voided' => 'badge-soft-danger'][$this->status];
    }

    public function title(): string
    {
        return $this->patient?->name ?? $this->label ?? 'Pembeli umum';
    }
}
