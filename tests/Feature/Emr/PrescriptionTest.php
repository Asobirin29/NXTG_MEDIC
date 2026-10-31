<?php

namespace Tests\Feature\Emr;

use App\Modules\Cds\Models\AlertOverride;
use App\Modules\Cds\Models\InteractionRule;
use App\Modules\Emr\Enums\PrescriptionStatus;
use App\Modules\Emr\Models\Icd10;
use App\Modules\Emr\Models\Prescription;
use App\Modules\Facility\Models\PaymentType;
use App\Modules\Facility\Models\Poli;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Drug;
use App\Modules\Inventory\Models\Substance;
use App\Modules\Inventory\Services\DrugService;
use App\Modules\Patient\Enums\EncounterStatus;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use App\Modules\Patient\Models\PatientAllergy;
use App\Modules\Patient\Services\RegistrationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PrescriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    private User $dokter;

    private Encounter $encounter;

    private Icd10 $icd;

    /** @var array<string, Drug> */
    private array $drugs = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->kasir = $this->makeUser(Role::Kasir);
        $this->dokter = $this->makeUser(Role::Dokter, $this->kasir->tenant);
        $this->icd = Icd10::create(['code' => 'J06.9', 'name_id' => 'ISPA']);

        app(TenantContext::class)->runAs($this->kasir->tenant_id, function () {
            $s = collect(['Parasetamol', 'Warfarin', 'Asam Asetilsalisilat', 'Amoksisilin', 'Kafein'])
                ->mapWithKeys(fn ($n) => [$n => Substance::create(['name' => $n])]);

            InteractionRule::create(['substance_a_id' => $s['Warfarin']->id, 'substance_b_id' => $s['Asam Asetilsalisilat']->id,
                'severity' => 'major', 'effect' => 'Risiko perdarahan.', 'is_active' => true]);

            $make = fn (string $name, array $subs) => app(DrugService::class)->save(new Drug,
                ['name' => $name, 'dosage_form' => 'tablet', 'category' => 'keras', 'base_unit' => 'tablet', 'min_stock' => 0, 'is_active' => true],
                array_map(fn ($n) => ['substance_id' => $s[$n]->id], $subs),
                [['unit_name' => 'Tablet', 'quantity_in_base' => 1], ['unit_name' => 'Strip', 'quantity_in_base' => 10, 'is_default_sale' => true]],
                []);

            $this->drugs = [
                'pct' => $make('Paracetamol 500', ['Parasetamol']),
                'pctx' => $make('Panadol Extra', ['Parasetamol', 'Kafein']),
                'warf' => $make('Warfarin 2', ['Warfarin']),
                'asa' => $make('Aspirin 80', ['Asam Asetilsalisilat']),
                'amox' => $make('Amoxicillin 500', ['Amoksisilin']),
            ];

            $patient = Patient::factory()->for($this->kasir->tenant)->create();
            PatientAllergy::create(['patient_id' => $patient->id, 'type' => 'drug', 'substance_id' => $s['Amoksisilin']->id, 'reaction' => 'Ruam']);

            $this->encounter = app(RegistrationService::class)->register(
                $patient, Poli::create(['code' => 'UMUM', 'name' => 'Umum']),
                PaymentType::create(['code' => 'UMUM', 'name' => 'Umum', 'patient_pays' => true]), null, $this->kasir,
            );
        });

        $this->actingAs($this->dokter)->post(route('emr.start', $this->encounter));
        $this->actingAs($this->dokter)->postJson(route('emr.diagnoses.store', $this->encounter), ['icd10_id' => $this->icd->id, 'type' => 'primary']);
    }

    private function add(string $key, int $qty = 1, string $signa = '3 x 1 tablet sesudah makan'): TestResponse
    {
        $drug = $this->drugs[$key];

        return $this->actingAs($this->dokter)->postJson(route('emr.prescription.store', $this->encounter), [
            'drug_id' => $drug->id, 'drug_packaging_id' => $drug->packagings->firstWhere('unit_name', 'Strip')->id,
            'quantity' => $qty, 'signa' => $signa,
        ]);
    }

    private function finish(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->dokter)->put(route('emr.finish', $this->encounter), ['subjective' => 'Batuk', 'overrides' => $overrides]);
    }

    public function test_item_converts_package_to_base_units_and_snapshots_names(): void
    {
        $this->add('pct', 2)->assertOk()->assertJsonPath('count', 1)->assertJsonPath('alerts', []);

        $item = Prescription::firstOrFail()->items->first();
        $this->assertSame(PrescriptionStatus::Draft, Prescription::first()->status);
        $this->assertSame(['Paracetamol 500', 'Strip', 2, 20], [$item->drug_name, $item->unit_name, $item->quantity, $item->quantity_base]);

        // Ubah master obat → resep tetap memakai nama saat diresepkan.
        $this->drugs['pct']->update(['name' => 'Nama Baru']);
        $this->assertSame('Paracetamol 500', $item->fresh()->drug_name);
    }

    public function test_signa_required_and_same_drug_not_twice(): void
    {
        $this->add('pct', 1, '')->assertStatus(422)->assertJsonValidationErrors('signa');
        $this->add('pct')->assertOk();
        $this->add('pct')->assertStatus(422);
    }

    public function test_packaging_must_belong_to_drug(): void
    {
        $this->actingAs($this->dokter)->postJson(route('emr.prescription.store', $this->encounter), [
            'drug_id' => $this->drugs['pct']->id, 'drug_packaging_id' => $this->drugs['warf']->packagings->first()->id,
            'quantity' => 1, 'signa' => '1x1',
        ])->assertNotFound();
    }

    public function test_cds_detects_allergy_interaction_and_duplicate(): void
    {
        $this->add('amox')->assertJsonPath('alerts.0.type', 'allergy')->assertJsonPath('alerts.0.requires_override', true);

        $this->add('warf');
        $keys = collect($this->add('asa')->json('alerts'))->pluck('type')->all();
        $this->assertSame(['allergy', 'interaction'], $keys);

        $this->add('pct');
        $types = collect($this->add('pctx')->json('alerts'))->pluck('severity', 'type')->all();
        $this->assertSame('moderate', $types['duplicate']);
    }

    public function test_finish_without_prescription_goes_to_billing(): void
    {
        $this->finish()->assertRedirect(route('emr.index'));
        $this->assertSame(EncounterStatus::ReadyBilling, $this->encounter->fresh()->status);
    }

    public function test_finish_with_prescription_sends_to_pharmacy(): void
    {
        $this->add('pct');
        $this->finish()->assertRedirect(route('emr.index'));

        $prescription = Prescription::firstOrFail();
        $this->assertSame(EncounterStatus::WaitingPharmacy, $this->encounter->fresh()->status);
        $this->assertSame(PrescriptionStatus::PendingVerification, $prescription->status);
        $this->assertNotNull($prescription->sent_at);
    }

    public function test_major_alert_blocks_finish_without_reason_and_rolls_back(): void
    {
        $this->add('warf');
        $this->add('asa');

        $this->finish()->assertSessionHasErrors();
        $this->finish(['interaction:'.InteractionRule::first()->id.':x' => 'alasan tidak cocok kunci'])->assertSessionHasErrors();

        $encounter = $this->encounter->fresh();
        $this->assertSame(EncounterStatus::InConsultation, $encounter->status);
        $this->assertFalse($encounter->clinicalNote->isLocked());
        $this->assertSame(PrescriptionStatus::Draft, Prescription::first()->status);
        $this->assertSame(0, AlertOverride::count());
    }

    public function test_major_alert_override_is_recorded(): void
    {
        $this->add('warf');
        $key = $this->add('asa')->json('alerts.0.key');

        $this->finish([$key => 'singkat'])->assertSessionHasErrors("overrides.{$key}");
        $this->finish([$key => 'Pasien pasca pasang stent, INR dipantau ketat oleh dokter jantung.'])->assertSessionHasNoErrors();

        $override = AlertOverride::firstOrFail();
        $this->assertSame([$this->dokter->id, 'prescribe', 'interaction', $key], [$override->user_id, $override->context, $override->alert_type, $key]);
        $this->assertSame(Prescription::first()->id, $override->prescription_id);
        $this->assertSame(EncounterStatus::WaitingPharmacy, $this->encounter->fresh()->status);
    }

    public function test_prescription_locked_after_finish(): void
    {
        $this->add('pct');
        $this->finish();

        $this->add('amox')->assertStatus(422);
        $item = Prescription::first()->items->first();
        $this->actingAs($this->dokter)->deleteJson(route('emr.prescription.destroy', [$this->encounter, $item]))->assertStatus(422);
    }

    public function test_copy_last_prescription(): void
    {
        $this->add('pct', 2, '3 x 1 tablet');
        $allergyKey = $this->add('amox')->json('alerts.0.key');
        $this->finish([$allergyKey => 'Riwayat alergi diragukan, sudah dikonfirmasi pasien.'])->assertSessionHasNoErrors();

        // Kunjungan berikutnya (hari lain), dokter menyalin resep lama.
        $this->encounter->forceFill(['status' => EncounterStatus::Completed])->saveQuietly();
        Carbon::setTestNow(now()->addDays(7));
        $next = app(TenantContext::class)->runAs($this->kasir->tenant_id, fn () => app(RegistrationService::class)->register(
            $this->encounter->patient, $this->encounter->poli, $this->encounter->paymentType, null, $this->kasir));
        $this->actingAs($this->dokter)->post(route('emr.start', $next));
        $this->drugs['amox']->update(['is_active' => false]);

        $this->actingAs($this->dokter)->postJson(route('emr.prescription.copy', $next))
            ->assertOk()->assertJsonPath('count', 1)->assertJsonPath('message', fn ($m) => str_contains($m, 'Dilewati: Amoxicillin 500'));

        $copied = $next->fresh()->prescription->items->first();
        $this->assertSame(['Paracetamol 500', 2, '3 x 1 tablet'], [$copied->drug_name, $copied->quantity, $copied->signa]);
        Carbon::setTestNow();
    }

    public function test_drug_search_returns_packagings_and_respects_role(): void
    {
        $this->actingAs($this->dokter)->getJson(route('drugs.search', ['q' => 'parasetamol']))
            ->assertOk()->assertJsonCount(2)->assertJsonPath('0.packagings.1.unit_name', 'Strip')->assertJsonPath('0.stock', 0);

        $perawat = $this->makeUser(Role::Perawat, $this->kasir->tenant);
        $this->actingAs($perawat)->getJson(route('drugs.search', ['q' => 'para']))->assertForbidden();
        $this->actingAs($perawat)->postJson(route('emr.prescription.store', $this->encounter), [])->assertForbidden();
    }
}
