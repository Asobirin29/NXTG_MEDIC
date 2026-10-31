<?php

namespace App\Modules\Patient\Services;

use App\Modules\Patient\Models\Patient;
use App\Support\Security\BlindIndex;
use App\Support\Sequence\SequenceGenerator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PatientService
{
    public function __construct(
        private TenantContext $context,
        private SequenceGenerator $sequences,
        private BlindIndex $blindIndex,
    ) {}

    public function create(array $data): Patient
    {
        $this->ensureUniqueNik($data['nik'] ?? null);

        return DB::transaction(function () use ($data) {
            $number = $this->sequences->next($this->context->id(), 'medical_record_number');

            return Patient::create($data + [
                'medical_record_number' => str_pad((string) $number, 6, '0', STR_PAD_LEFT),
                'is_active' => true,
            ]);
        });
    }

    public function update(Patient $patient, array $data): Patient
    {
        // NIK yang sama tidak di-set ulang: enkripsi selalu memberi ciphertext baru
        // sehingga akan tercatat sebagai "perubahan" palsu di audit log.
        if (BlindIndex::normalizeNik($data['nik'] ?? null) === BlindIndex::normalizeNik($patient->nik)) {
            unset($data['nik']);
        } else {
            $this->ensureUniqueNik($data['nik'] ?? null, $patient);
        }

        $patient->update($data);

        return $patient;
    }

    private function ensureUniqueNik(?string $nik, ?Patient $ignore = null): void
    {
        $index = $this->blindIndex->nik($nik);
        if ($index === null) {
            return;
        }

        $existing = Patient::where('nik_blind_index', $index)
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'nik' => "NIK sudah terdaftar atas nama {$existing->name} (No. RM {$existing->medical_record_number}).",
            ]);
        }
    }
}
