<?php

namespace App\Modules\Cds\Services;

use App\Modules\Cds\Models\AlertOverride;
use App\Modules\Cds\Models\InteractionRule;
use App\Modules\Cds\Support\CdsEngine;
use App\Modules\Cds\Support\ClinicalAlert;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Drug;
use App\Modules\Patient\Enums\AllergyType;
use App\Modules\Patient\Models\Patient;
use Illuminate\Validation\ValidationException;

/**
 * Menyiapkan data (obat, alergi pasien, aturan interaksi faskes) untuk
 * CdsEngine, dan mencatat override peringatan berat beserta alasannya.
 */
class CdsService
{
    public const MIN_REASON_LENGTH = 10;

    public function __construct(private CdsEngine $engine) {}

    /**
     * @param  iterable<int>  $drugIds
     * @return list<ClinicalAlert>
     */
    public function check(?Patient $patient, iterable $drugIds): array
    {
        $drugs = Drug::with('substances')->whereIn('id', collect($drugIds)->unique()->all())->get();
        if ($drugs->isEmpty()) {
            return [];
        }

        $substanceIds = $drugs->flatMap(fn (Drug $d) => $d->substances->pluck('id'))->unique()->values();

        $allergies = $patient
            ? $patient->allergies()->with('substance')->where('type', AllergyType::Drug)->whereIn('substance_id', $substanceIds)->get()
                ->map(fn ($a) => ['substance_id' => $a->substance_id, 'name' => $a->substance->name, 'reaction' => $a->reaction])->all()
            : [];

        $rules = InteractionRule::where('is_active', true)
            ->whereIn('substance_a_id', $substanceIds)
            ->whereIn('substance_b_id', $substanceIds)
            ->get()
            ->mapWithKeys(fn (InteractionRule $r) => [CdsEngine::pairKey($r->substance_a_id, $r->substance_b_id) => [
                'id' => $r->id, 'severity' => $r->severity, 'effect' => $r->effect, 'recommendation' => $r->recommendation,
            ]])->all();

        return $this->engine->check(
            $drugs->map(fn (Drug $d) => ['id' => $d->id, 'name' => $d->name, 'substances' => $d->substances->pluck('name', 'id')->all()])->values()->all(),
            $allergies,
            $rules,
        );
    }

    /**
     * Pastikan setiap peringatan berat punya alasan tertulis, lalu catat.
     *
     * @param  list<ClinicalAlert>  $alerts
     * @param  array<string, string|null>  $reasons  alert key => alasan
     * @param  array{context: string, patient_id?: ?int, prescription_id?: ?int, sale_id?: ?int}  $context
     */
    public function recordOverrides(array $alerts, array $reasons, User $user, array $context): void
    {
        $missing = [];
        foreach ($alerts as $alert) {
            if ($alert->requiresOverride() && mb_strlen(trim((string) ($reasons[$alert->key] ?? ''))) < self::MIN_REASON_LENGTH) {
                $missing["overrides.{$alert->key}"] = "Peringatan berat \"{$alert->title}\" memerlukan alasan klinis tertulis (minimal ".self::MIN_REASON_LENGTH.' karakter) atau ganti obatnya.';
            }
        }
        if ($missing) {
            throw ValidationException::withMessages($missing);
        }

        foreach ($alerts as $alert) {
            if (! $alert->requiresOverride()) {
                continue;
            }

            AlertOverride::create($context + [
                'user_id' => $user->id,
                'alert_type' => $alert->type,
                'severity' => $alert->severity,
                'alert_key' => $alert->key,
                'interaction_rule_id' => $alert->ruleId,
                'message' => $alert->message,
                'reason' => trim($reasons[$alert->key]),
                'ip_address' => request()?->ip(),
            ]);
        }
    }
}
