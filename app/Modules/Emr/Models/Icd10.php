<?php

namespace App\Modules\Emr\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Kamus ICD-10 (referensi global, bukan data faskes). */
class Icd10 extends Model
{
    protected $table = 'icd10_masters';

    public $timestamps = false;

    protected $fillable = ['code', 'name_id', 'name_en', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Kode diawali term, atau nama Indonesia/Inggris mengandung term. Kode yang cocok diurutkan lebih dulu. */
    public function scopeSearch(Builder $query, string $term): void
    {
        $term = trim($term);
        $code = strtoupper($term);

        $query->where('is_active', true)
            ->where(fn ($q) => $q
                ->where('code', 'like', "{$code}%")
                ->orWhere('name_id', 'like', "%{$term}%")
                ->orWhere('name_en', 'like', "%{$term}%"))
            ->orderByRaw('CASE WHEN code LIKE ? THEN 0 ELSE 1 END', ["{$code}%"])
            ->orderBy('code');
    }
}
