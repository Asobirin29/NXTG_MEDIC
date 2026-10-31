<?php

namespace Tests\Feature\Patient;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Substance;
use App\Modules\Patient\Models\Patient;
use App\Modules\Patient\Models\PatientAllergy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PatientTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    private const NIK = '3273011204790001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->kasir = $this->makeUser(Role::Kasir);
    }

    private function create(array $overrides = []): Patient
    {
        $this->actingAs($this->kasir)->post(route('patients.store'), $overrides + [
            'name' => 'Budi Hartono', 'birth_date' => '1979-04-12', 'gender' => 'L', 'nik' => self::NIK,
        ])->assertSessionHasNoErrors();

        return Patient::latest('id')->firstOrFail();
    }

    public function test_medical_record_numbers_are_sequential_per_tenant(): void
    {
        $first = $this->create();
        $second = $this->create(['name' => 'Sari', 'nik' => '3273017009910002']);

        $this->assertSame('000001', $first->medical_record_number);
        $this->assertSame('000002', $second->medical_record_number);

        // Faskes lain mulai dari 000001 lagi.
        $this->kasir = $this->makeUser(Role::Kasir);
        $this->assertSame('000001', $this->create(['nik' => null])->medical_record_number);
    }

    public function test_nik_is_encrypted_at_rest_and_searchable_by_blind_index(): void
    {
        $patient = $this->create(['nik' => '3273 0112 0479 0001']);

        $raw = DB::table('patients')->where('id', $patient->id)->first();
        $this->assertStringNotContainsString(self::NIK, $raw->nik);
        $this->assertSame(64, strlen($raw->nik_blind_index));
        $this->assertSame(self::NIK, $patient->fresh()->nik);

        $this->actingAs($this->kasir)->get(route('patients.index', ['q' => self::NIK]))->assertSee('Budi Hartono');
        $this->actingAs($this->kasir)->get(route('patients.index', ['q' => '3273011204790009']))->assertDontSee('Budi Hartono');
        // Daftar hanya menampilkan NIK tersamar.
        $this->actingAs($this->kasir)->get(route('patients.index'))->assertDontSee(self::NIK);
    }

    public function test_duplicate_nik_rejected_within_tenant_only(): void
    {
        $this->create();

        $this->actingAs($this->kasir)->post(route('patients.store'), [
            'name' => 'Orang Lain', 'birth_date' => '1990-01-01', 'gender' => 'L', 'nik' => self::NIK,
        ])->assertSessionHasErrors('nik');

        $this->kasir = $this->makeUser(Role::Kasir);
        $this->create(['name' => 'Di faskes lain']);
    }

    public function test_search_by_name_rm_phone_and_insurance(): void
    {
        $this->create(['phone' => '081234567890', 'insurance_number' => '0001234567891']);

        foreach (['budi', '000001', '4567890', '0001234567891'] as $term) {
            $this->actingAs($this->kasir)->get(route('patients.index', ['q' => $term]))->assertSee('Budi Hartono', false);
        }
    }

    public function test_unchanged_nik_is_not_rewritten_on_update(): void
    {
        $patient = $this->create();
        $before = DB::table('patients')->where('id', $patient->id)->value('nik');

        $this->actingAs($this->kasir)->put(route('patients.update', $patient), [
            'name' => 'Budi H.', 'birth_date' => '1979-04-12', 'gender' => 'L', 'nik' => self::NIK, 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame($before, DB::table('patients')->where('id', $patient->id)->value('nik'));
        $this->assertSame('Budi H.', $patient->fresh()->name);
    }

    public function test_viewing_patient_is_audited(): void
    {
        $patient = $this->create();
        $this->actingAs($this->kasir)->get(route('patients.show', $patient))->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'viewed', 'auditable_id' => $patient->id, 'user_id' => $this->kasir->id]);
    }

    public function test_drug_allergy_by_substance_and_food_allergy(): void
    {
        $patient = $this->create();
        $amox = Substance::create(['name' => 'Amoksisilin']);

        $this->actingAs($this->kasir)->post(route('patients.allergies.store', $patient), ['type' => 'drug', 'substance_id' => $amox->id, 'severity' => 'major'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->kasir)->post(route('patients.allergies.store', $patient), ['type' => 'drug', 'substance_id' => $amox->id])
            ->assertSessionHasErrorsIn('allergy', 'substance_id');
        $this->actingAs($this->kasir)->post(route('patients.allergies.store', $patient), ['type' => 'food'])
            ->assertSessionHasErrorsIn('allergy', 'allergen_name');
        $this->actingAs($this->kasir)->post(route('patients.allergies.store', $patient), ['type' => 'food', 'allergen_name' => 'Udang'])
            ->assertSessionHasNoErrors();

        $labels = $patient->allergies()->with('substance')->get()->map->allergenLabel()->all();
        $this->assertSame(['Amoksisilin', 'Udang'], $labels);

        $this->actingAs($this->kasir)->get(route('patients.show', $patient))->assertSee('Amoksisilin, Udang');
    }

    public function test_allergy_cannot_be_deleted_through_another_patient(): void
    {
        $a = $this->create();
        $b = $this->create(['name' => 'Lain', 'nik' => null]);
        $allergy = PatientAllergy::create(['patient_id' => $a->id, 'type' => 'food', 'allergen_name' => 'Udang']);

        $this->actingAs($this->kasir)->delete(route('patients.allergies.destroy', [$b, $allergy]))->assertNotFound();
        $this->assertModelExists($allergy);
    }

    public function test_apoteker_can_view_but_not_create_patients(): void
    {
        $patient = $this->create();
        $apoteker = $this->makeUser(Role::Apoteker, $this->kasir->tenant);

        $this->actingAs($apoteker)->get(route('patients.show', $patient))->assertOk();
        $this->actingAs($apoteker)->get(route('patients.create'))->assertForbidden();
        $this->actingAs($apoteker)->post(route('patients.allergies.store', $patient), ['type' => 'food', 'allergen_name' => 'X'])->assertForbidden();
    }

    public function test_patient_of_other_tenant_not_reachable(): void
    {
        $patient = $this->create();
        $other = $this->makeUser(Role::Kasir);

        $this->actingAs($other)->get(route('patients.show', $patient))->assertNotFound();
    }
}
