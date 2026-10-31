<?php

namespace App\Support\Audit;

/**
 * Catat create/update/delete model ke audit_logs. Atribut $hidden (password,
 * PIN, NIK) tidak pernah ikut tersimpan — hanya nama kolomnya yang dicatat.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => $model->writeAudit('created', null, $model->auditValues($model->getAttributes())));

        static::updated(function ($model) {
            $changes = $model->auditValues($model->getChanges());
            unset($changes['updated_at']);
            if (! $changes) {
                return;
            }
            $old = array_intersect_key($model->auditValues($model->getRawOriginal()), $changes);
            $model->writeAudit('updated', $old, $changes);
        });

        static::deleted(fn ($model) => $model->writeAudit('deleted', $model->auditValues($model->getRawOriginal()), null));
    }

    protected function auditValues(array $values): array
    {
        foreach ($this->getHidden() as $hidden) {
            if (array_key_exists($hidden, $values)) {
                $values[$hidden] = '[disembunyikan]';
            }
        }

        return $values;
    }

    protected function writeAudit(string $action, ?array $old, ?array $new): void
    {
        app(AuditLogger::class)->log($action, $this, $old, $new);
    }
}
