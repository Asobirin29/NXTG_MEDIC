<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Facility\Models\PaymentType;
use App\Modules\Inventory\Enums\DrugCategory;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Drug extends Model
{
    use Auditable, BelongsToTenant;

    public const DOSAGE_FORMS = [
        'tablet', 'tablet salut', 'kaplet', 'kapsul', 'sirup', 'suspensi', 'drops', 'injeksi',
        'infus', 'salep', 'krim', 'gel', 'tetes mata', 'tetes telinga', 'inhaler', 'suppositoria', 'puyer', 'lainnya',
    ];

    public const BASE_UNITS = ['tablet', 'kaplet', 'kapsul', 'ml', 'botol', 'ampul', 'vial', 'tube', 'sachet', 'pcs', 'supp', 'pot'];

    protected $fillable = [
        'kfa_code', 'name', 'dosage_form', 'strength', 'category', 'base_unit', 'barcode', 'min_stock', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category' => DrugCategory::class,
            'min_stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function substances(): BelongsToMany
    {
        return $this->belongsToMany(Substance::class, 'drug_substances')
            ->withPivot(['strength_value', 'strength_unit']);
    }

    public function packagings(): HasMany
    {
        return $this->hasMany(DrugPackaging::class)->orderBy('quantity_in_base');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(DrugPrice::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Daftar kemasan dalam format yang dipakai PackagingConverter. */
    public function packagingRows(): array
    {
        return $this->packagings->map(fn ($p) => ['unit_name' => $p->unit_name, 'quantity_in_base' => $p->quantity_in_base])->all();
    }

    public function priceFor(PaymentType|int $paymentType): ?int
    {
        $id = $paymentType instanceof PaymentType ? $paymentType->id : $paymentType;

        return $this->prices->firstWhere('payment_type_id', $id)?->price_per_base;
    }

    /** "Paracetamol 500 mg + Kafein 50 mg" — komposisi zat aktif untuk ditampilkan. */
    public function compositionLabel(): string
    {
        return $this->substances->map(function (Substance $s) {
            $value = $s->pivot->strength_value;
            $strength = $value !== null
                ? ' '.rtrim(rtrim((string) $value, '0'), '.').' '.$s->pivot->strength_unit
                : '';

            return $s->name.$strength;
        })->implode(' + ');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(fn ($q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhere('kfa_code', $term)
            ->orWhere('barcode', $term)
            ->orWhereHas('substances', fn ($s) => $s->where('name', 'like', "%{$term}%"))
            ->orWhereHas('packagings', fn ($p) => $p->where('barcode', $term)));
    }
}
