<?php

namespace App\Modules\Import\Importers;

use App\Modules\Identity\Models\User;
use App\Modules\Patient\Models\Patient;
use App\Modules\Patient\Services\PatientService;
use App\Support\Security\BlindIndex;
use Illuminate\Support\Facades\DB;

/**
 * Data pasien dari sistem lama. No. RM lama boleh dibawa; nomor otomatis
 * berikutnya dilanjutkan setelah nomor terbesar agar tidak bentrok.
 */
class PatientImporter extends Importer
{
    /** @var array<string, true> */
    private array $nik = [];

    /** @var array<string, true> */
    private array $mrn = [];

    public function __construct(private PatientService $patients, private BlindIndex $blindIndex) {}

    public function type(): string
    {
        return 'patients';
    }

    public function label(): string
    {
        return 'Data pasien';
    }

    public function ability(): string
    {
        return 'settings.manage';
    }

    public function description(): string
    {
        return 'NIK disimpan terenkripsi. Kosongkan no_rm agar dibuatkan nomor baru. Alergi diisi setelah impor lewat halaman pasien.';
    }

    public function columns(): array
    {
        return [
            'nama' => [true, 'Nama lengkap', 'Siti Aminah'],
            'tanggal_lahir' => [true, 'dd/mm/yyyy', '17/08/1985'],
            'jenis_kelamin' => [true, 'L / P', 'P'],
            'nik' => [false, '16 digit', null],
            'no_bpjs' => [false, 'Nomor kartu BPJS/asuransi', null],
            'telepon' => [false, 'Nomor HP', '081234567890'],
            'alamat' => [false, 'Alamat', 'Bandung'],
            'golongan_darah' => [false, 'A / B / AB / O', null],
            'no_rm' => [false, 'No. RM dari sistem lama', null],
        ];
    }

    public function previewRow(array $row): array
    {
        $nik = BlindIndex::normalizeNik($this->str($row, 'nik'));

        return ['nik' => $nik ? str_repeat('•', 12).substr($nik, -4) : null] + $row;
    }

    public function prepare(): void
    {
        $this->nik = Patient::query()->whereNotNull('nik_blind_index')->pluck('nik_blind_index')->mapWithKeys(fn ($i) => [$i => true])->all();
        $this->mrn = Patient::query()->pluck('medical_record_number')->mapWithKeys(fn ($n) => [$n => true])->all();
    }

    public function validate(array $row): array
    {
        $errors = [];
        $p = $this->parse($row, $errors);

        if ($p['nik']) {
            $index = $this->blindIndex->nik($p['nik']);
            if (isset($this->nik[$index])) {
                $errors[] = 'NIK sudah terdaftar atau muncul dua kali di file.';
            }
            $this->nik[$index] = true;
        }
        if ($p['medical_record_number']) {
            if (isset($this->mrn[$p['medical_record_number']])) {
                $errors[] = "No. RM {$p['medical_record_number']} sudah dipakai.";
            }
            $this->mrn[$p['medical_record_number']] = true;
        }

        return $errors;
    }

    public function import(array $rows, User $user, string $sourceName): int
    {
        return DB::transaction(function () use ($rows, $user) {
            $maxNumeric = 0;
            foreach ($rows as $row) {
                $errors = [];
                $p = $this->parse($row, $errors);
                $data = array_filter($p, fn ($v) => $v !== null);
                $this->patients->create($data);
                if ($p['medical_record_number'] && ctype_digit($p['medical_record_number'])) {
                    $maxNumeric = max($maxNumeric, (int) $p['medical_record_number']);
                }
            }

            // Nomor RM otomatis berikutnya harus melewati nomor lama yang diimpor.
            if ($maxNumeric > 0) {
                DB::table('sequences')->where('tenant_id', $user->tenant_id)->where('name', 'medical_record_number')
                    ->where('value', '<', $maxNumeric)->update(['value' => $maxNumeric]);
            }

            return count($rows);
        });
    }

    private function parse(array $row, array &$errors): array
    {
        $name = $this->required($row, 'nama', 'Nama', $errors);

        $birth = $this->date($row, 'tanggal_lahir');
        if ($birth === null) {
            $errors[] = 'Tanggal lahir wajib diisi.';
        } elseif ($birth === false) {
            $errors[] = 'Tanggal lahir tidak terbaca; gunakan format dd/mm/yyyy.';
        } elseif ($birth->isFuture() || $birth->year < 1900) {
            $errors[] = 'Tanggal lahir tidak masuk akal.';
        }

        $genderText = self::lower($this->required($row, 'jenis_kelamin', 'Jenis kelamin', $errors));
        $gender = match (true) {
            in_array($genderText, ['l', 'laki-laki', 'laki laki', 'pria'], true) => 'L',
            in_array($genderText, ['p', 'perempuan', 'wanita'], true) => 'P',
            default => null,
        };
        if ($genderText !== '' && ! $gender) {
            $errors[] = 'Jenis kelamin harus L atau P.';
        }

        $nik = BlindIndex::normalizeNik($this->str($row, 'nik'));
        if ($nik !== null && ! preg_match('/^\d{16}$/', $nik)) {
            $errors[] = 'NIK harus 16 digit.';
        }

        $blood = mb_strtoupper((string) $this->str($row, 'golongan_darah'));
        if ($blood !== '' && ! in_array($blood, ['A', 'B', 'AB', 'O'], true)) {
            $errors[] = 'Golongan darah harus A, B, AB, atau O.';
        }

        $mrn = $this->str($row, 'no_rm');
        if ($mrn !== null && mb_strlen($mrn) > 20) {
            $errors[] = 'No. RM maksimal 20 karakter.';
        }

        return [
            'name' => $name,
            'birth_date' => $birth instanceof \DateTimeInterface ? $birth->toDateString() : null,
            'gender' => $gender,
            'nik' => $nik ?: null,
            'insurance_number' => $this->str($row, 'no_bpjs'),
            'phone' => $this->str($row, 'telepon'),
            'address' => $this->str($row, 'alamat'),
            'blood_type' => $blood ?: null,
            'medical_record_number' => $mrn,
        ];
    }
}
