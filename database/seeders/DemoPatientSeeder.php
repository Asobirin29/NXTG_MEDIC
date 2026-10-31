<?php

namespace Database\Seeders;

use App\Modules\Emr\Models\Icd10;
use App\Modules\Emr\Services\EmrService;
use App\Modules\Facility\Models\MedicalService;
use App\Modules\Facility\Models\PaymentType;
use App\Modules\Facility\Models\Poli;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Inventory\Models\Substance;
use App\Modules\Patient\Enums\EncounterStatus;
use App\Modules\Patient\Models\PatientAllergy;
use App\Modules\Patient\Services\EncounterStateMachine;
use App\Modules\Patient\Services\PatientService;
use App\Modules\Patient\Services\RegistrationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/** Pasien fiktif + antrean hari ini untuk faskes demo. NIK fiktif, bukan milik siapa pun. */
class DemoPatientSeeder extends Seeder
{
    /** [nama, tgl lahir, JK, NIK, no. BPJS, alergi obat (zat aktif), alergi lain] */
    private const PATIENTS = [
        ['Budi Hartono', '1979-04-12', 'L', '3273011204790001', '0001234567891', ['Amoksisilin'], []],
        ['Sari Wulandari', '1991-09-30', 'P', '3273017009910002', null, [], ['Udang']],
        ['Aisyah Putri', '2021-02-15', 'P', null, '0001234567893', [], []],
        ['Hendra Gunawan', '1958-12-01', 'L', '3273010112580004', '0001234567894', ['Asam Asetilsalisilat'], []],
        ['Rina Marlina', '1985-06-21', 'P', '3273016106850005', null, [], []],
        ['Dimas Saputra', '2016-11-05', 'L', null, null, ['Ibuprofen'], ['Debu']],
        ['Ketut Wirawan', '1966-03-17', 'L', '3273011703660007', '0001234567897', [], []],
        ['Maria Christina', '2000-08-08', 'P', '3273014808000008', null, [], []],
    ];

    public function run(PatientService $patients, RegistrationService $registration, EncounterStateMachine $machine, EmrService $emr, TenantContext $context): void
    {
        $tenant = Tenant::where('code', 'KPSS')->firstOrFail();
        $kasir = $tenant->users()->where('role', Role::Kasir)->firstOrFail();
        $perawat = $tenant->users()->where('role', Role::Perawat)->firstOrFail();
        $dokter = $tenant->users()->where('role', Role::Dokter)->firstOrFail();

        $context->runAs($tenant->id, function () use ($patients, $registration, $machine, $emr, $kasir, $perawat, $dokter) {
            $substances = Substance::pluck('id', 'name');
            $poli = Poli::where('code', 'UMUM')->firstOrFail();
            $umum = PaymentType::where('code', 'UMUM')->firstOrFail();
            $bpjs = PaymentType::where('code', 'BPJS')->firstOrFail();

            $created = [];
            foreach (self::PATIENTS as [$name, $birth, $gender, $nik, $bpjsNumber, $drugAllergies, $otherAllergies]) {
                $patient = $patients->create([
                    'name' => $name, 'birth_date' => $birth, 'gender' => $gender, 'nik' => $nik,
                    'insurance_number' => $bpjsNumber, 'phone' => '0812'.str_pad((string) (crc32($name) % 100000000), 8, '0', STR_PAD_LEFT),
                    'address' => 'Bandung',
                ]);

                foreach ($drugAllergies as $substance) {
                    PatientAllergy::create(['patient_id' => $patient->id, 'type' => 'drug', 'substance_id' => $substances[$substance], 'severity' => 'major', 'reaction' => 'Ruam dan gatal', 'recorded_by' => $kasir->id]);
                }
                foreach ($otherAllergies as $allergen) {
                    PatientAllergy::create(['patient_id' => $patient->id, 'type' => 'food', 'allergen_name' => $allergen, 'severity' => 'moderate', 'recorded_by' => $kasir->id]);
                }

                $created[] = $patient;
            }

            // Riwayat: kunjungan 2 minggu lalu yang sudah selesai & terkunci.
            $past = [
                [$created[0], ['systolic' => 128, 'diastolic' => 84, 'heart_rate' => 88, 'temperature' => 38.2, 'weight' => 72, 'height' => 168],
                    'Batuk pilek 3 hari, demam naik turun.', 'Faring hiperemis (+). Pulmo: vesikuler, rhonki (-).', 'J06.9', 'Istirahat, banyak minum. Kontrol bila memberat.'],
                [$created[3], ['systolic' => 158, 'diastolic' => 96, 'heart_rate' => 80, 'temperature' => 36.6, 'weight' => 81, 'height' => 165],
                    'Kontrol hipertensi, kadang pusing.', 'KU baik. Jantung & paru dbn.', 'I10', 'Diet rendah garam. Kontrol 1 bulan.'],
            ];
            Carbon::setTestNow(now()->subDays(14)->setTime(9, 30));
            foreach ($past as [$patient, $vitals, $s, $o, $icd, $p]) {
                $encounter = $registration->register($patient, $poli, $patient->insurance_number ? $bpjs : $umum, $dokter, $kasir);
                $emr->recordVitals($encounter, $vitals + ['chief_complaint' => $s], $perawat);
                $emr->startConsultation($encounter, $dokter);
                $emr->addDiagnosis($encounter, Icd10::where('code', $icd)->firstOrFail(), 'primary');
                $emr->addService($encounter, MedicalService::where('code', 'KONS-UMUM')->firstOrFail(), 1, $dokter);
                $emr->finish($encounter, ['subjective' => $s, 'objective' => $o, 'plan' => $p], $dokter);
                $machine->transition($encounter, EncounterStatus::Completed);
            }
            Carbon::setTestNow();

            // Antrean hari ini: [tanda vital (null = belum diasesmen), sudah dipanggil dokter?].
            $today = [
                [['systolic' => 130, 'diastolic' => 85, 'heart_rate' => 84, 'temperature' => 37.8, 'weight' => 72, 'chief_complaint' => 'Batuk berdahak 5 hari, demam ringan.'], true],
                [['systolic' => 110, 'diastolic' => 70, 'heart_rate' => 76, 'temperature' => 36.7, 'weight' => 55, 'chief_complaint' => 'Gatal-gatal setelah makan seafood.'], false],
                [['heart_rate' => 110, 'temperature' => 38.9, 'spo2' => 98, 'weight' => 17.5, 'height' => 108, 'chief_complaint' => 'Demam 2 hari, nafsu makan turun.'], false],
                [null, false],
                [null, false],
            ];
            foreach ($today as $i => [$vitals, $called]) {
                $encounter = $registration->register($created[$i], $poli, $created[$i]->insurance_number ? $bpjs : $umum, $i === 0 ? $dokter : null, $kasir);
                if ($vitals) {
                    $emr->recordVitals($encounter, $vitals, $perawat);
                }
                if ($called) {
                    $emr->startConsultation($encounter, $dokter);
                }
            }
        });
    }
}
