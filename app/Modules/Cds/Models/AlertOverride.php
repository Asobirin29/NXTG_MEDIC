<?php

namespace App\Modules\Cds\Models;

use App\Modules\Cds\Enums\Severity;
use App\Modules\Identity\Models\User;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Catatan "peringatan dilewati": siapa, kapan, konteks, aturan, dan alasan. Append-only. */
class AlertOverride extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'context', 'patient_id', 'prescription_id', 'sale_id', 'alert_type', 'severity',
        'alert_key', 'interaction_rule_id', 'message', 'reason', 'ip_address',
    ];

    protected function casts(): array
    {
        return ['severity' => Severity::class];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Catatan override tidak boleh diubah.'));
        static::deleting(fn () => throw new LogicException('Catatan override tidak boleh dihapus.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
