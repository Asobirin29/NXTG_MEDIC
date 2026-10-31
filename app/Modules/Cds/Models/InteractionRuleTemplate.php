<?php

namespace App\Modules\Cds\Models;

use App\Modules\Cds\Enums\Severity;
use App\Modules\Inventory\Models\Substance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Template interaksi global, dikurasi apoteker mitra, disalin ke tiap faskes. */
class InteractionRuleTemplate extends Model
{
    protected $fillable = ['substance_a_id', 'substance_b_id', 'severity', 'effect', 'recommendation', 'version'];

    protected function casts(): array
    {
        return ['severity' => Severity::class];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $rule) => InteractionRule::normalizePair($rule));
    }

    public function substanceA(): BelongsTo
    {
        return $this->belongsTo(Substance::class, 'substance_a_id');
    }

    public function substanceB(): BelongsTo
    {
        return $this->belongsTo(Substance::class, 'substance_b_id');
    }
}
