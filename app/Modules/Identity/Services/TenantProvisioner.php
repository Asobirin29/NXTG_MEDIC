<?php

namespace App\Modules\Identity\Services;

use App\Modules\Cds\Services\InteractionTemplateSync;
use App\Modules\Emr\Models\SoapTemplate;
use App\Modules\Facility\Models\PaymentType;
use App\Modules\Facility\Models\Poli;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Membuat faskes baru beserta admin pertama dan data awal: jenis pembayaran
 * bawaan, Poli Umum, dan salinan template interaksi obat.
 */
class TenantProvisioner
{
    public function __construct(
        private TenantContext $context,
        private InteractionTemplateSync $interactions,
    ) {}

    public function provision(array $tenantData, array $adminData): Tenant
    {
        return DB::transaction(function () use ($tenantData, $adminData) {
            $tenant = Tenant::create($tenantData + ['settings' => ['theme' => Tenant::DEFAULT_THEME]]);

            User::create($adminData + ['tenant_id' => $tenant->id, 'is_active' => true]);

            $this->context->runAs($tenant->id, function () {
                foreach (PaymentType::DEFAULTS as $i => [$code, $name, $patientPays]) {
                    PaymentType::create(['code' => $code, 'name' => $name, 'patient_pays' => $patientPays, 'sort_order' => $i + 1]);
                }

                Poli::create(['code' => 'UMUM', 'name' => 'Poli Umum']);

                foreach (SoapTemplate::DEFAULTS as [$section, $title, $body]) {
                    SoapTemplate::create(['section' => $section, 'title' => $title, 'body' => $body]);
                }
            });

            $this->interactions->syncTenant($tenant->id);

            return $tenant;
        });
    }
}
