<?php

namespace App\Modules\Facility\Models;

use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalServicePrice extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['medical_service_id', 'payment_type_id', 'price'];

    protected function casts(): array
    {
        return ['price' => 'integer'];
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }
}
