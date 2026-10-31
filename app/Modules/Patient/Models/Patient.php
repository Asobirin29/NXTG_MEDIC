<?php

namespace App\Modules\Patient\Models;

use App\Support\Audit\Auditable;
use App\Support\Security\BlindIndex;
use App\Support\Tenancy\BelongsToTenant;
use Carbon\CarbonInterface;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(PatientFactory::class)]
class Patient extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    /** Batas usia anak: berat badan ditonjolkan di layar dokter & farmasi (PRD §6.2). */
    public const CHILD_AGE_LIMIT = 18;

    protected $fillable = [
        'medical_record_number', 'name', 'birth_date', 'gender', 'nik', 'phone', 'address',
        'insurance_number', 'blood_type', 'is_active',
    ];

    protected $hidden = ['nik', 'nik_blind_index'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'nik' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Blind index selalu mengikuti NIK; NIK disimpan dalam bentuk angka saja.
        static::saving(function (self $patient) {
            if ($patient->isDirty('nik')) {
                $nik = BlindIndex::normalizeNik($patient->nik);
                $patient->nik = $nik;
                $patient->nik_blind_index = app(BlindIndex::class)->nik($nik);
            }
        });
    }

    public function allergies(): HasMany
    {
        return $this->hasMany(PatientAllergy::class)->orderByRaw("FIELD(type, 'drug', 'food', 'environment', 'other')");
    }

    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class);
    }

    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $digits = preg_replace('/\D/', '', $term);

        // 16 digit = NIK → cocokkan lewat blind index, tidak pernah LIKE pada kolom terenkripsi.
        if (strlen($digits) === 16 && strlen($digits) === strlen(preg_replace('/[\s.-]/', '', $term))) {
            $query->where('nik_blind_index', app(BlindIndex::class)->nik($digits));

            return;
        }

        $query->where(fn ($q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhere('medical_record_number', 'like', "{$term}%")
            ->when($digits !== '' && strlen($digits) >= 4, fn ($q) => $q
                ->orWhere('phone', 'like', "%{$digits}%")
                ->orWhere('insurance_number', $digits)));
    }

    public function ageAt(?CarbonInterface $date = null): array
    {
        $diff = $this->birth_date->diff($date ?? now());

        return ['years' => $diff->y, 'months' => $diff->m, 'days' => $diff->d];
    }

    /** "34 th", "2 th 3 bl", atau "18 hr" untuk bayi. */
    public function ageLabel(): string
    {
        ['years' => $y, 'months' => $m, 'days' => $d] = $this->ageAt();

        return match (true) {
            $y >= 5 => "{$y} th",
            $y >= 1 => trim("{$y} th".($m ? " {$m} bl" : '')),
            $m >= 1 => "{$m} bl",
            default => "{$d} hr",
        };
    }

    public function isChild(): bool
    {
        return $this->ageAt()['years'] < self::CHILD_AGE_LIMIT;
    }

    public function genderLabel(): string
    {
        return $this->gender === 'L' ? 'Laki-laki' : 'Perempuan';
    }

    /** NIK disamarkan untuk daftar: 3201********0001. */
    public function maskedNik(): ?string
    {
        $nik = $this->nik;

        return $nik ? substr($nik, 0, 4).str_repeat('•', max(strlen($nik) - 8, 0)).substr($nik, -4) : null;
    }
}
