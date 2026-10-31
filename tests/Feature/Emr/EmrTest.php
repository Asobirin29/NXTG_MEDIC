<?php

namespace Tests\Feature\Emr;

use App\Modules\Emr\Exceptions\MedicalRecordLocked;
use App\Modules\Emr\Models\ClinicalNote;
use App\Modules\Emr\Models\EncounterService;
use App\Modules\Emr\Models\Icd10;
use App\Modules\Facility\Models\MedicalService;
use App\Modules\Facility\Models\MedicalServicePrice;
use App\Modules\Facility\Models\PaymentType;
use App\Modules\Facility\Models\Poli;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Patient\Enums\EncounterStatus;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use App\Modules\Patient\Services\RegistrationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EmrTest extends TestCase
{
    use RefreshDatabase;

    private User $perawat;

    private User $dokter;

    private Encounter $encounter;

    private Icd10 $ispa;

    private Icd10 $demam;

    private MedicalService $konsul;

    protected function setUp(): void
    {
        parent::setUp();

        $kasir = $this->makeUser(Role::Kasir);
        $tenant = $kasir->tenant;
        $this->perawat = $this->makeUser(Role::Perawat, $tenant);
        $this->dokter = $this->makeUser(Role::Dokter, $tenant);

        $this->ispa = Icd10::create(['code' => 'J06.9', 'name_id' => 'ISPA']);
        $this->demam = Icd10::create(['code' => 'R50.9', 'name_id' => 'Demam']);

        $this->encounter = app(TenantContext::class)->runAs($tenant->id, function () use ($kasir, $tenant) {
            $poli = Poli::create(['code' => 'UMUM', 'name' => 'Poli Umum']);
            $umum = PaymentType::create(['code' => 'UMUM', 'name' => 'Umum', 'patient_pays' => true]);
            $this->konsul = MedicalService::create(['code' => 'KONS', 'name' => 'Konsultasi']);
            MedicalServicePrice::create(['medical_service_id' => $this->konsul->id, 'payment_type_id' => $umum->id, 'price' => 50000]);

            $patient = Patient::factory()->for($tenant)->create(['birth_date' => '1980-01-01']);

            return app(RegistrationService::class)->register($patient, $poli, $umum, null, $kasir);
        });
    }

    private function vitals(array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->perawat)->post(route('emr.assessment.store', $this->encounter), $data + [
            'systolic' => 120, 'diastolic' => 80, 'temperature' => 37.5, 'weight' => 70, 'chief_complaint' => 'Batuk',
        ]);
    }

    private function start(): void
    {
        $this->actingAs($this->dokter)->post(route('emr.start', $this->encounter))->assertRedirect(route('emr.consultation', $this->encounter));
    }

    private function addDx(Icd10 $icd, string $type = 'primary')
    {
        return $this->actingAs($this->dokter)->postJson(route('emr.diagnoses.store', $this->encounter), ['icd10_id' => $icd->id, 'type' => $type]);
    }

    public function test_nurse_assessment_moves_encounter_to_nurse_assessed(): void
    {
        $this->vitals()->assertSessionHasNoErrors()->assertRedirect(route('emr.index'));

        $this->assertSame(EncounterStatus::NurseAssessed, $this->encounter->fresh()->status);
        $vital = $this->encounter->fresh()->latestVital;
        $this->assertSame('120/80', $vital->bloodPressure());
        $this->assertSame($this->perawat->id, $vital->recorded_by);
    }

    public function test_vital_validation(): void
    {
        $this->vitals(['systolic' => 80, 'diastolic' => 120])->assertSessionHasErrors('diastolic');
        $this->vitals(['temperature' => 55])->assertSessionHasErrors('temperature');

        $this->actingAs($this->perawat)->post(route('emr.assessment.store', $this->encounter), ['chief_complaint' => 'x'])
            ->assertSessionHasErrors('systolic');
    }

    public function test_child_requires_weight(): void
    {
        $this->encounter->patient->update(['birth_date' => now()->subYears(4)]);

        $this->vitals(['weight' => ''])->assertSessionHasErrors('weight');
    }

    public function test_doctor_starts_consultation_and_is_assigned(): void
    {
        $this->vitals();
        $this->start();

        $encounter = $this->encounter->fresh();
        $this->assertSame(EncounterStatus::InConsultation, $encounter->status);
        $this->assertSame($this->dokter->id, $encounter->doctor_id);
        $this->assertNotNull($encounter->clinicalNote);
    }

    public function test_doctor_can_start_without_nurse_assessment(): void
    {
        $this->start();
        $this->assertSame(EncounterStatus::InConsultation, $this->encounter->fresh()->status);
    }

    public function test_draft_autosave_returns_json(): void
    {
        $this->start();

        $this->actingAs($this->dokter)->putJson(route('emr.soap', $this->encounter), ['subjective' => 'Batuk 3 hari'])
            ->assertOk()->assertJsonStructure(['saved_at']);

        $this->assertSame('Batuk 3 hari', $this->encounter->fresh()->clinicalNote->subjective);
    }

    public function test_cannot_write_soap_before_starting(): void
    {
        $this->actingAs($this->dokter)->putJson(route('emr.soap', $this->encounter), ['subjective' => 'x'])
            ->assertStatus(422)->assertJson(['message' => 'Mulai pemeriksaan terlebih dahulu.']);
    }

    public function test_diagnosis_primary_rules(): void
    {
        $this->start();

        // Diagnosis pertama otomatis primer walau dipilih sekunder.
        $this->addDx($this->ispa, 'secondary')->assertOk()->assertJsonStructure(['html']);
        $this->assertSame('primary', $this->encounter->diagnoses()->first()->type);

        // Primer baru menurunkan primer lama.
        $this->addDx($this->demam, 'primary');
        $types = $this->encounter->diagnoses()->with('icd10')->get()->mapWithKeys(fn ($d) => [$d->icd10->code => $d->type])->all();
        $this->assertSame(['R50.9' => 'primary', 'J06.9' => 'secondary'], $types);

        $this->addDx($this->demam)->assertStatus(422);

        // Hapus primer → sekunder naik menjadi primer.
        $primary = $this->encounter->diagnoses()->where('type', 'primary')->first();
        $this->actingAs($this->dokter)->deleteJson(route('emr.diagnoses.destroy', [$this->encounter, $primary]))->assertOk();
        $this->assertSame('primary', $this->encounter->diagnoses()->first()->type);
    }

    public function test_service_price_snapshot_and_missing_tariff(): void
    {
        $this->start();

        $this->actingAs($this->dokter)->postJson(route('emr.services.store', $this->encounter), ['medical_service_id' => $this->konsul->id, 'quantity' => 2])
            ->assertOk()->assertSee('Rp100.000');

        // Tarif berubah setelahnya tidak memengaruhi tindakan yang sudah tercatat.
        MedicalServicePrice::query()->update(['price' => 99000]);
        $this->assertSame(50000, EncounterService::firstOrFail()->unit_price);

        $noTariff = app(TenantContext::class)->runAs($this->dokter->tenant_id, fn () => MedicalService::create(['code' => 'X', 'name' => 'Tanpa tarif']));
        $this->actingAs($this->dokter)->postJson(route('emr.services.store', $this->encounter), ['medical_service_id' => $noTariff->id, 'quantity' => 1])
            ->assertStatus(422);
    }

    public function test_finish_requires_subjective_and_primary_diagnosis(): void
    {
        $this->start();

        $this->actingAs($this->dokter)->put(route('emr.finish', $this->encounter), ['subjective' => ''])
            ->assertSessionHas('error', 'Belum bisa diselesaikan, lengkapi: Subjective (keluhan), diagnosis primer ICD-10.');
        $this->assertSame(EncounterStatus::InConsultation, $this->encounter->fresh()->status);
    }

    public function test_finish_locks_record_and_forwards_to_billing(): void
    {
        $this->start();
        $this->addDx($this->ispa);

        $this->actingAs($this->dokter)->put(route('emr.finish', $this->encounter), ['subjective' => 'Batuk', 'plan' => 'Istirahat'])
            ->assertRedirect(route('emr.index'));

        $encounter = $this->encounter->fresh();
        $this->assertSame(EncounterStatus::ReadyBilling, $encounter->status);
        $this->assertTrue($encounter->clinicalNote->isLocked());
        $this->assertSame('Istirahat', $encounter->clinicalNote->plan);

        // Setelah dikunci: tidak bisa diubah lewat aplikasi maupun model.
        $this->actingAs($this->dokter)->putJson(route('emr.soap', $this->encounter), ['subjective' => 'Diubah'])->assertStatus(422);
        $this->addDx($this->demam)->assertStatus(422);
        $this->expectException(MedicalRecordLocked::class);
        $encounter->clinicalNote->update(['subjective' => 'Diubah diam-diam']);
    }

    public function test_addendum_only_after_lock_and_keeps_original(): void
    {
        $this->start();
        $this->actingAs($this->dokter)->post(route('emr.addendum', $this->encounter), ['content' => 'x'])->assertSessionHas('error');

        $this->addDx($this->ispa);
        $this->actingAs($this->dokter)->put(route('emr.finish', $this->encounter), ['subjective' => 'Batuk']);
        $this->actingAs($this->dokter)->post(route('emr.addendum', $this->encounter), ['content' => 'Koreksi: batuk 5 hari'])->assertSessionHas('success');

        $note = ClinicalNote::firstOrFail();
        $this->assertSame('Batuk', $note->subjective);
        $this->assertSame('Koreksi: batuk 5 hari', $note->addenda()->first()->content);
        $this->actingAs($this->dokter)->get(route('emr.consultation', $this->encounter))->assertSee('Koreksi: batuk 5 hari');
    }

    public function test_previous_visits_appear_in_history(): void
    {
        $this->start();
        $this->addDx($this->ispa);
        $this->actingAs($this->dokter)->put(route('emr.finish', $this->encounter), ['subjective' => 'Kunjungan lama']);

        // Kunjungan berikutnya keesokan hari.
        Carbon::setTestNow(now()->addDay());
        $kasir = User::where('role', 'kasir')->first();
        $next = app(TenantContext::class)->runAs($kasir->tenant_id, fn () => app(RegistrationService::class)->register(
            $this->encounter->patient, $this->encounter->poli, $this->encounter->paymentType, null, $kasir,
        ));

        $this->actingAs($this->dokter)->get(route('emr.consultation', $next))
            ->assertOk()->assertSee('Riwayat kunjungan')->assertSee('J06.9 ISPA')->assertSee('Kunjungan lama');
        Carbon::setTestNow();
    }

    public function test_role_boundaries(): void
    {
        $this->actingAs($this->perawat)->get(route('emr.consultation', $this->encounter))->assertForbidden();
        $this->actingAs($this->dokter)->get(route('emr.assessment', $this->encounter))->assertOk();
        $this->actingAs($this->perawat)->get(route('emr.index'))->assertOk()->assertSee($this->encounter->patient->name);

        $other = $this->makeUser(Role::Dokter);
        $this->actingAs($other)->get(route('emr.consultation', $this->encounter))->assertNotFound();
    }
}
