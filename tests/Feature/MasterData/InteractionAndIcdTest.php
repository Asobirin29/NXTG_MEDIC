<?php

namespace Tests\Feature\MasterData;

use App\Modules\Cds\Models\InteractionRule;
use App\Modules\Cds\Models\InteractionRuleTemplate;
use App\Modules\Identity\Enums\Role;
use App\Modules\Inventory\Models\Substance;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InteractionAndIcdTest extends TestCase
{
    use RefreshDatabase;

    public function test_rule_pair_is_normalized_and_reverse_duplicate_rejected(): void
    {
        $apoteker = $this->makeUser(Role::Apoteker);
        $a = Substance::create(['name' => 'Zat A']);
        $b = Substance::create(['name' => 'Zat B']);

        $this->actingAs($apoteker)->post(route('interactions.store'), [
            'substance_a_id' => $b->id, 'substance_b_id' => $a->id, 'severity' => 'major', 'effect' => 'Uji', 'is_active' => 1,
        ])->assertRedirect(route('interactions.index'));

        $rule = InteractionRule::firstOrFail();
        $this->assertSame([$a->id, $b->id], [$rule->substance_a_id, $rule->substance_b_id]);

        $this->actingAs($apoteker)->post(route('interactions.store'), [
            'substance_a_id' => $a->id, 'substance_b_id' => $b->id, 'severity' => 'minor', 'effect' => 'Dobel', 'is_active' => 1,
        ])->assertSessionHasErrors('substance_b_id');

        $this->actingAs($apoteker)->post(route('interactions.store'), [
            'substance_a_id' => $a->id, 'substance_b_id' => $a->id, 'severity' => 'minor', 'effect' => 'Sama', 'is_active' => 1,
        ])->assertSessionHasErrors('substance_b_id');
    }

    public function test_sync_only_adds_missing_template_rules(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $apoteker = $this->makeUser(Role::Apoteker);
        $total = InteractionRuleTemplate::count();

        $template = InteractionRuleTemplate::firstOrFail();
        app(TenantContext::class)->runAs($apoteker->tenant_id, fn () => InteractionRule::create([
            'substance_a_id' => $template->substance_a_id, 'substance_b_id' => $template->substance_b_id,
            'severity' => 'minor', 'effect' => 'Versi faskes', 'is_active' => true,
        ]));

        $this->actingAs($apoteker)->post(route('interactions.sync'))->assertSessionHas('success');

        app(TenantContext::class)->runAs($apoteker->tenant_id, function () use ($total, $template) {
            $this->assertSame($total, InteractionRule::count());
            // Aturan buatan faskes tidak ditimpa template.
            $this->assertSame('Versi faskes', InteractionRule::forPair($template->substance_a_id, $template->substance_b_id)->first()->effect);
        });

        $this->actingAs($apoteker)->post(route('interactions.sync'))->assertSessionHas('success', 'Semua aturan template sudah ada di faskes ini.');
    }

    public function test_dokter_cannot_edit_interactions(): void
    {
        $this->actingAs($this->makeUser(Role::Dokter))->get(route('interactions.create'))->assertForbidden();
    }

    public function test_icd10_search_prioritizes_code_prefix(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $user = $this->makeUser(Role::Dokter);

        $this->actingAs($user)->getJson(route('icd10.search', ['q' => 'i10']))
            ->assertOk()->assertJsonPath('0.code', 'I10');

        $this->actingAs($user)->getJson(route('icd10.search', ['q' => 'hipertensi']))
            ->assertJsonFragment(['code' => 'I10']);

        $this->actingAs($user)->getJson(route('icd10.search', ['q' => 'common cold']))
            ->assertJsonFragment(['code' => 'J00']);

        $this->actingAs($user)->getJson(route('icd10.search', ['q' => 'x']))->assertExactJson([]);
    }

    public function test_icd10_import_command(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'icd').'.csv';
        file_put_contents($file, "kode,nama,name\nA00,Kolera,Cholera\nA00.1,\"Kolera, eltor\",Cholera due to Vibrio cholerae 01 biovar eltor\nbukan-kode,x,y\n");

        $this->artisan('medic:import-icd10', ['file' => $file])
            ->expectsOutputToContain('2 kode ICD-10 diimpor/diperbarui. 1 baris dilewati.')
            ->assertSuccessful();

        $this->assertDatabaseHas('icd10_masters', ['code' => 'A00.1', 'name_id' => 'Kolera, eltor']);
        unlink($file);
    }
}
