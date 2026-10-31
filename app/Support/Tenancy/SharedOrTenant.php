<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;

/**
 * Untuk data referensi (mis. zat aktif): baris tenant_id NULL dipakai bersama
 * semua faskes, baris dengan tenant_id hanya terlihat oleh faskes pembuatnya.
 * Data yang dibuat di dalam konteks faskes otomatis menjadi milik faskes itu.
 */
trait SharedOrTenant
{
    public static function bootSharedOrTenant(): void
    {
        static::addGlobalScope('shared_or_tenant', function (Builder $builder) {
            $context = app(TenantContext::class);

            if ($context->has()) {
                $column = $builder->getModel()->qualifyColumn('tenant_id');
                $builder->where(fn ($q) => $q->whereNull($column)->orWhere($column, $context->id()));
            }
        });

        static::creating(function ($model) {
            $context = app(TenantContext::class);

            if ($context->has()) {
                $model->tenant_id = $context->id();
            }
        });
    }

    public function isShared(): bool
    {
        return $this->tenant_id === null;
    }
}
