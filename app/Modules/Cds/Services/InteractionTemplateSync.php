<?php

namespace App\Modules\Cds\Services;

use App\Modules\Cds\Models\InteractionRule;
use App\Modules\Cds\Models\InteractionRuleTemplate;
use App\Support\Tenancy\TenantContext;

/**
 * Salin template interaksi ke faskes. Pasangan yang sudah ada di faskes
 * (hasil salinan lama atau buatan apoteker sendiri) tidak ditimpa.
 */
class InteractionTemplateSync
{
    public function __construct(private TenantContext $context) {}

    /** @return int jumlah aturan baru yang ditambahkan */
    public function syncTenant(int $tenantId): int
    {
        return $this->context->runAs($tenantId, function () {
            $existing = InteractionRule::query()
                ->get(['substance_a_id', 'substance_b_id'])
                ->map(fn ($r) => $r->substance_a_id.'-'.$r->substance_b_id)
                ->flip();

            $added = 0;

            foreach (InteractionRuleTemplate::all() as $template) {
                if ($existing->has($template->substance_a_id.'-'.$template->substance_b_id)) {
                    continue;
                }

                InteractionRule::create([
                    'template_id' => $template->id,
                    'template_version' => $template->version,
                    'substance_a_id' => $template->substance_a_id,
                    'substance_b_id' => $template->substance_b_id,
                    'severity' => $template->severity,
                    'effect' => $template->effect,
                    'recommendation' => $template->recommendation,
                    'is_active' => true,
                ]);
                $added++;
            }

            return $added;
        });
    }
}
