<?php

namespace Tests\Feature\Patient;

use App\Modules\Facility\Models\PaymentType;
use App\Modules\Facility\Models\Poli;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Patient\Enums\EncounterStatus;
use App\Modules\Patient\Exceptions\InvalidEncounterTransition;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use App\Modules\Patient\Services\EncounterStateMachine;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    private Poli $umum;

    private Poli $gigi;

    private PaymentType $cash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kasir = $this->makeUser(Role::Kasir);

        [$this->umum, $this->gigi, $this->cash] = app(TenantContext::class)->runAs($this->kasir->tenant_id, fn () => [
            Poli::create(['code' => 'UMUM', 'name' => 'Poli Umum']),
            Poli::create(['code' => 'GIGI', 'name' => 'Poli Gigi']),
            PaymentType::create(['code' => 'UMUM', 'name' => 'Umum', 'patient_pays' => true]),
        ]);
    }

    private function patient(): Patient
    {
        return Patient::factory()->for($this->kasir->tenant)->create();
    }

    private function register(Patient $patient, ?Poli $poli = null)
    {
        return $this->actingAs($this->kasir)->post(route('registration.store'), [
            'patient_id' => $patient->id, 'poli_id' => ($poli ?? $this->umum)->id, 'payment_type_id' => $this->cash->id,
        ]);
    }

    public function test_queue_numbers_increment_per_poli_and_reset_daily(): void
    {
        $this->register($this->patient())->assertSessionHasNoErrors()->assertRedirect(route('registration.index'));
        $this->register($this->patient());
        $this->register($this->patient(), $this->gigi);

        $labels = Encounter::with('poli')->orderBy('id')->get()->map->queueLabel()->all();
        $this->assertSame(['UMUM-001', 'UMUM-002', 'GIGI-001'], $labels);

        Carbon::setTestNow(now()->addDay());
        $this->register($this->patient());
        $this->assertSame('UMUM-001', Encounter::with('poli')->latest('id')->first()->queueLabel());
        Carbon::setTestNow();
    }

    public function test_encounter_starts_queued_with_payment_type(): void
    {
        $patient = $this->patient();
        $this->register($patient);

        $encounter = Encounter::firstOrFail();
        $this->assertSame(EncounterStatus::Queued, $encounter->status);
        $this->assertSame($this->cash->id, $encounter->payment_type_id);
        $this->assertSame($this->kasir->id, $encounter->registered_by);
        $this->actingAs($this->kasir)->get(route('registration.index'))->assertSee($patient->name)->assertSee('UMUM-001');
    }

    public function test_patient_cannot_be_queued_twice_to_same_poli_same_day(): void
    {
        $patient = $this->patient();
        $this->register($patient);

        $this->register($patient)->assertSessionHasErrors('poli_id');
        $this->register($patient, $this->gigi)->assertSessionHasNoErrors();
        $this->assertSame(2, Encounter::count());
    }

    public function test_can_requeue_after_cancellation(): void
    {
        $patient = $this->patient();
        $this->register($patient);
        $encounter = Encounter::firstOrFail();

        $this->actingAs($this->kasir)->post(route('registration.cancel', $encounter), ['reason' => 'Salah pilih poli'])->assertSessionHas('success');
        $this->assertSame(EncounterStatus::Cancelled, $encounter->fresh()->status);
        $this->assertSame('Salah pilih poli', $encounter->fresh()->cancel_reason);

        $this->register($patient)->assertSessionHasNoErrors();
    }

    public function test_cancel_requires_reason(): void
    {
        $this->register($this->patient());

        $this->actingAs($this->kasir)->post(route('registration.cancel', Encounter::firstOrFail()), ['reason' => ''])
            ->assertSessionHasErrors('reason');
    }

    public function test_cannot_cancel_after_consultation_started(): void
    {
        $this->register($this->patient());
        $encounter = Encounter::firstOrFail();
        app(EncounterStateMachine::class)->transition($encounter, EncounterStatus::InConsultation);

        $this->actingAs($this->kasir)->post(route('registration.cancel', $encounter), ['reason' => 'x'])->assertSessionHas('error');
        $this->assertSame(EncounterStatus::InConsultation, $encounter->fresh()->status);
    }

    public function test_state_machine_sets_timestamps_and_audits(): void
    {
        $this->register($this->patient());
        $encounter = Encounter::firstOrFail();
        $machine = app(EncounterStateMachine::class);

        $machine->transition($encounter, EncounterStatus::NurseAssessed);
        $machine->transition($encounter, EncounterStatus::InConsultation);

        $fresh = $encounter->fresh();
        $this->assertNotNull($fresh->nurse_assessed_at);
        $this->assertNotNull($fresh->consultation_started_at);
        $this->assertSame(EncounterStatus::InConsultation, $encounter->status, 'Model asli ikut diperbarui');
        $this->assertDatabaseHas('audit_logs', ['action' => 'encounter_status', 'auditable_id' => $encounter->id]);

        $this->expectException(InvalidEncounterTransition::class);
        $machine->transition($encounter, EncounterStatus::Completed);
    }

    public function test_rejects_poli_or_payment_type_of_other_tenant(): void
    {
        $other = $this->makeUser(Role::Kasir);
        $foreignPoli = app(TenantContext::class)->runAs($other->tenant_id, fn () => Poli::create(['code' => 'X', 'name' => 'Asing']));

        $this->actingAs($this->kasir)->post(route('registration.store'), [
            'patient_id' => $this->patient()->id, 'poli_id' => $foreignPoli->id, 'payment_type_id' => $this->cash->id,
        ])->assertSessionHasErrors('poli_id');
    }

    public function test_inactive_patient_cannot_be_registered(): void
    {
        $patient = $this->patient();
        $patient->update(['is_active' => false]);

        $this->register($patient)->assertSessionHasErrors('patient_id');
    }

    public function test_dokter_cannot_register_visits(): void
    {
        $dokter = $this->makeUser(Role::Dokter, $this->kasir->tenant);

        $this->actingAs($dokter)->post(route('registration.store'), [
            'patient_id' => $this->patient()->id, 'poli_id' => $this->umum->id, 'payment_type_id' => $this->cash->id,
        ])->assertForbidden();
    }
}
