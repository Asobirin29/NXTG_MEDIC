<?php

namespace App\Modules\Facility\Models;

use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentType extends Model
{
    use Auditable, BelongsToTenant;

    /** Jenis bawaan untuk faskes baru. [code, nama, pasien membayar]. */
    public const DEFAULTS = [
        ['UMUM', 'Umum', true],
        ['BPJS', 'BPJS Kesehatan', false],
        ['ASURANSI', 'Asuransi', true],
        ['GRATIS', 'Program / Gratis', false],
    ];

    protected $fillable = ['code', 'name', 'patient_pays', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['patient_pays' => 'boolean', 'is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}
