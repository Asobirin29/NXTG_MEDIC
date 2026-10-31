<?php

namespace App\Support\Tenancy;

use App\Modules\Identity\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengganti RLS PostgreSQL: setiap model operasional otomatis difilter dan
 * diisi tenant_id dari TenantContext, sehingga query lupa-filter tidak bisa
 * membaca data faskes lain.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            $context = app(TenantContext::class);

            if ($context->has()) {
                // Konteks aktif selalu menang, walau input mencoba mengisi tenant lain.
                $model->tenant_id = $context->id();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
