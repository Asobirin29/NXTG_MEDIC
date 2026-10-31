<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Facility\Models\PaymentType;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Harga jual per satuan terkecil per jenis pembayaran (Rupiah). */
class DrugPrice extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['drug_id', 'payment_type_id', 'price_per_base'];

    protected function casts(): array
    {
        return ['price_per_base' => 'integer'];
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }
}
