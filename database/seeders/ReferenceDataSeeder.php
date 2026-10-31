<?php

namespace Database\Seeders;

use App\Modules\Cds\Models\InteractionRuleTemplate;
use App\Modules\Emr\Models\Icd10;
use App\Modules\Inventory\Models\Substance;
use Illuminate\Database\Seeder;
use RuntimeException;

/** Data referensi global: zat aktif, ICD-10 contoh, template interaksi. Aman dijalankan ulang. */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach (require database_path('data/substances.php') as [$name, $atc]) {
            Substance::withoutGlobalScopes()->updateOrCreate(['name' => $name, 'tenant_id' => null], ['atc_code' => $atc]);
        }

        foreach (require database_path('data/icd10.php') as [$code, $id, $en]) {
            Icd10::updateOrCreate(['code' => $code], ['name_id' => $id, 'name_en' => $en]);
        }

        $substances = Substance::withoutGlobalScopes()->whereNull('tenant_id')->pluck('id', 'name');

        foreach (require database_path('data/interaction_templates.php') as [$a, $b, $severity, $effect, $advice]) {
            if (! isset($substances[$a], $substances[$b])) {
                throw new RuntimeException("Zat aktif template interaksi tidak ditemukan: {$a} / {$b}");
            }

            $ids = [$substances[$a], $substances[$b]];
            sort($ids);

            InteractionRuleTemplate::updateOrCreate(
                ['substance_a_id' => $ids[0], 'substance_b_id' => $ids[1]],
                ['severity' => $severity, 'effect' => $effect, 'recommendation' => $advice],
            );
        }
    }
}
