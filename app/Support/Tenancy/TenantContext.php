<?php

namespace App\Support\Tenancy;

/**
 * Faskes aktif untuk request ini. Diisi middleware SetTenantContext dari user
 * yang login; dibaca TenantScope untuk memfilter semua query operasional.
 */
class TenantContext
{
    private ?int $tenantId = null;

    public function set(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function has(): bool
    {
        return $this->tenantId !== null;
    }

    /** Jalankan callback atas nama tenant tertentu (seeder, job, impor). */
    public function runAs(int $tenantId, callable $callback): mixed
    {
        $previous = $this->tenantId;
        $this->tenantId = $tenantId;

        try {
            return $callback();
        } finally {
            $this->tenantId = $previous;
        }
    }
}
