<?php

namespace App\Modules\Facility\Models;

use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Master tindakan/jasa poli beserta tarif per jenis pembayaran. */
class MedicalService extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['poli_id', 'code', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function poli(): BelongsTo
    {
        return $this->belongsTo(Poli::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(MedicalServicePrice::class);
    }

    public function priceFor(PaymentType|int $paymentType): ?int
    {
        $id = $paymentType instanceof PaymentType ? $paymentType->id : $paymentType;

        return $this->prices->firstWhere('payment_type_id', $id)?->price;
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
