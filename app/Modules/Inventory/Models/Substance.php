<?php

namespace App\Modules\Inventory\Models;

use App\Support\Audit\Auditable;
use App\Support\Tenancy\SharedOrTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Zat aktif. Dasar deteksi alergi & interaksi lintas merek (PRD §6.1). */
class Substance extends Model
{
    use Auditable, SharedOrTenant;

    protected $fillable = ['name', 'atc_code'];

    public function drugs(): BelongsToMany
    {
        return $this->belongsToMany(Drug::class, 'drug_substances');
    }
}
