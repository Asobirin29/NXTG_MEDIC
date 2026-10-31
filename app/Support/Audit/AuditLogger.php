<?php

namespace App\Support\Audit;

use App\Modules\Identity\Models\AuditLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public function __construct(private TenantContext $tenant) {}

    public function log(
        string $action,
        ?Model $subject = null,
        ?array $old = null,
        ?array $new = null,
        ?string $reason = null,
        ?int $userId = null,
        ?int $tenantId = null,
    ): AuditLog {
        $request = request();

        return AuditLog::create([
            'tenant_id' => $tenantId ?? $this->tenant->id() ?? $subject?->getAttribute('tenant_id'),
            'user_id' => $userId ?? Auth::guard('web')->id(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'reason' => $reason,
            'ip_address' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 255) ?: null,
        ]);
    }
}
