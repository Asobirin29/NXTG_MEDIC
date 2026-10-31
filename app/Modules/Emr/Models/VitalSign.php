<?php

namespace App\Modules\Emr\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Patient\Models\Encounter;
use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VitalSign extends Model
{
    use Auditable, BelongsToTenant;

    /** Rentang masuk akal untuk validasi input (bukan rentang normal klinis). */
    public const RANGES = [
        'systolic' => [40, 300],
        'diastolic' => [20, 200],
        'heart_rate' => [20, 250],
        'respiratory_rate' => [4, 80],
        'temperature' => [30, 45],
        'spo2' => [50, 100],
        'weight' => [0.3, 400],
        'height' => [20, 250],
    ];

    public const MEASUREMENTS = ['systolic', 'diastolic', 'heart_rate', 'respiratory_rate', 'temperature', 'spo2', 'weight', 'height'];

    protected $fillable = [
        'encounter_id', 'systolic', 'diastolic', 'heart_rate', 'respiratory_rate', 'temperature', 'spo2',
        'weight', 'height', 'chief_complaint', 'recorded_by', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'temperature' => 'float',
            'weight' => 'float',
            'height' => 'float',
            'recorded_at' => 'datetime',
        ];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function bloodPressure(): ?string
    {
        return $this->systolic && $this->diastolic ? "{$this->systolic}/{$this->diastolic}" : null;
    }

    public function bmi(): ?float
    {
        return $this->weight && $this->height ? round($this->weight / (($this->height / 100) ** 2), 1) : null;
    }

    /** Ringkasan satu baris untuk disisipkan ke kolom Objective. */
    public function summary(): string
    {
        $num = fn (?float $v) => $v === null ? null : rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',');

        return collect([
            $this->bloodPressure() ? "TD {$this->bloodPressure()} mmHg" : null,
            $this->heart_rate ? "N {$this->heart_rate} x/mnt" : null,
            $this->respiratory_rate ? "RR {$this->respiratory_rate} x/mnt" : null,
            $this->temperature ? 'S '.$num($this->temperature).' °C' : null,
            $this->spo2 ? "SpO2 {$this->spo2}%" : null,
            $this->weight ? 'BB '.$num($this->weight).' kg' : null,
            $this->height ? 'TB '.$num($this->height).' cm' : null,
        ])->filter()->implode(', ');
    }
}
