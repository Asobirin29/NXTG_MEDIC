<?php

namespace App\Modules\Cds\Support;

use App\Modules\Cds\Enums\Severity;

/**
 * Pengecekan alergi, interaksi, dan duplikasi zat aktif (PRD §6.3).
 * Murni tanpa database: CdsService yang menyiapkan datanya. Ini memudahkan
 * pengujian dan memastikan logika yang sama dipakai di layar dokter & kasir.
 */
final class CdsEngine
{
    /**
     * @param  list<array{id: int, name: string, substances: array<int, string>}>  $drugs
     * @param  list<array{substance_id: int, name: string, reaction?: ?string}>  $allergies  alergi obat pasien
     * @param  array<string, array{id: int, severity: Severity, effect: string, recommendation: ?string}>  $rules  key "a-b" (a < b)
     * @return list<ClinicalAlert> diurutkan dari yang paling berat
     */
    public function check(array $drugs, array $allergies, array $rules): array
    {
        $alerts = array_merge(
            $this->allergyAlerts($drugs, $allergies),
            $this->interactionAlerts($drugs, $rules),
            $this->duplicateAlerts($drugs),
        );

        usort($alerts, fn (ClinicalAlert $a, ClinicalAlert $b) => [$b->severity->rank(), $a->key] <=> [$a->severity->rank(), $b->key]);

        return $alerts;
    }

    public static function pairKey(int $x, int $y): string
    {
        return min($x, $y).'-'.max($x, $y);
    }

    private function allergyAlerts(array $drugs, array $allergies): array
    {
        $alerts = [];
        $allergic = [];
        foreach ($allergies as $a) {
            $allergic[$a['substance_id']] = $a;
        }

        foreach ($drugs as $drug) {
            foreach ($drug['substances'] as $substanceId => $substanceName) {
                if (! isset($allergic[$substanceId])) {
                    continue;
                }

                $reaction = $allergic[$substanceId]['reaction'] ?? null;
                $alerts[] = new ClinicalAlert(
                    type: ClinicalAlert::ALLERGY,
                    severity: Severity::Major,
                    key: "allergy:{$substanceId}:{$drug['id']}",
                    title: "Pasien alergi {$substanceName}",
                    message: "{$drug['name']} mengandung {$substanceName}, zat aktif yang tercatat sebagai alergi pasien".($reaction ? " (reaksi: {$reaction})." : '.'),
                    recommendation: 'Ganti dengan obat tanpa zat aktif ini.',
                    drugIds: [$drug['id']],
                );
            }
        }

        return $alerts;
    }

    private function interactionAlerts(array $drugs, array $rules): array
    {
        $alerts = [];
        $count = count($drugs);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                [$d1, $d2] = [$drugs[$i], $drugs[$j]];

                foreach ($d1['substances'] as $s1 => $n1) {
                    foreach ($d2['substances'] as $s2 => $n2) {
                        $rule = $s1 !== $s2 ? ($rules[self::pairKey($s1, $s2)] ?? null) : null;
                        if (! $rule) {
                            continue;
                        }

                        $alerts[] = new ClinicalAlert(
                            type: ClinicalAlert::INTERACTION,
                            severity: $rule['severity'],
                            key: "interaction:{$rule['id']}:".self::pairKey($d1['id'], $d2['id']),
                            title: "{$n1} + {$n2}",
                            message: "{$d1['name']} dan {$d2['name']}: {$rule['effect']}",
                            recommendation: $rule['recommendation'],
                            drugIds: [$d1['id'], $d2['id']],
                            ruleId: $rule['id'],
                        );
                    }
                }
            }
        }

        return $alerts;
    }

    private function duplicateAlerts(array $drugs): array
    {
        $bySubstance = [];
        foreach ($drugs as $drug) {
            foreach ($drug['substances'] as $substanceId => $name) {
                $bySubstance[$substanceId]['name'] = $name;
                $bySubstance[$substanceId]['drugs'][$drug['id']] = $drug['name'];
            }
        }

        $alerts = [];
        foreach ($bySubstance as $substanceId => $entry) {
            if (count($entry['drugs']) < 2) {
                continue;
            }

            $ids = array_keys($entry['drugs']);
            sort($ids);

            $alerts[] = new ClinicalAlert(
                type: ClinicalAlert::DUPLICATE,
                severity: Severity::Moderate,
                key: "duplicate:{$substanceId}:".implode('-', $ids),
                title: "Duplikasi {$entry['name']}",
                message: implode(', ', $entry['drugs'])." sama-sama mengandung {$entry['name']}. Risiko dosis berlebih.",
                recommendation: 'Pertahankan salah satu obat saja, atau pastikan total dosis harian masih aman.',
                drugIds: $ids,
            );
        }

        return $alerts;
    }
}
