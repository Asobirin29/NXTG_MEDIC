<?php

namespace App\Modules\Cds\Models;

use App\Modules\Cds\Enums\Severity;
use App\Modules\Inventory\Models\Substance;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Aturan interaksi zat aktif A–B milik faskes (PRD §6.3). */
class InteractionRule extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = [
        'template_id', 'template_version', 'substance_a_id', 'substance_b_id',
        'severity', 'effect', 'recommendation', 'is_active',
    ];

    protected function casts(): array
    {
        return ['severity' => Severity::class, 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $rule) => self::normalizePair($rule));
    }

    /** Simpan pasangan terurut (a < b) supaya A–B dan B–A dianggap aturan yang sama. */
    public static function normalizePair(Model $rule): void
    {
        $a = (int) $rule->substance_a_id;
        $b = (int) $rule->substance_b_id;

        if ($a > $b) {
            $rule->substance_a_id = $b;
            $rule->substance_b_id = $a;
        }
    }

    public function substanceA(): BelongsTo
    {
        return $this->belongsTo(Substance::class, 'substance_a_id');
    }

    public function substanceB(): BelongsTo
    {
        return $this->belongsTo(Substance::class, 'substance_b_id');
    }

    public function scopeForPair(Builder $query, int $x, int $y): void
    {
        $query->where('substance_a_id', min($x, $y))->where('substance_b_id', max($x, $y));
    }
}
