<?php

namespace App\Modules\Inventory\Models;

use App\Support\Audit\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu tingkat kemasan: unit_name berisi quantity_in_base satuan terkecil. */
class DrugPackaging extends Model
{
    use Auditable;

    protected $fillable = ['unit_name', 'quantity_in_base', 'barcode', 'is_default_sale'];

    protected function casts(): array
    {
        return ['quantity_in_base' => 'integer', 'is_default_sale' => 'boolean'];
    }

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }
}
