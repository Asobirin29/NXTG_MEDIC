<?php

namespace Tests\Unit;

use App\Modules\Cds\Enums\Severity;
use App\Modules\Cds\Support\CdsEngine;
use App\Modules\Cds\Support\ClinicalAlert;
use PHPUnit\Framework\TestCase;

class CdsEngineTest extends TestCase
{
    // Zat aktif: 1 Parasetamol, 2 Kafein, 3 Warfarin, 4 Asam asetilsalisilat, 5 Amoksisilin, 6 Ibuprofen
    private const PCT = ['id' => 10, 'name' => 'Paracetamol 500', 'substances' => [1 => 'Parasetamol']];
    private const PCT_CAF = ['id' => 11, 'name' => 'Panadol Extra', 'substances' => [1 => 'Parasetamol', 2 => 'Kafein']];
    private const WARF = ['id' => 12, 'name' => 'Warfarin 2', 'substances' => [3 => 'Warfarin']];
    private const ASA = ['id' => 13, 'name' => 'Aspirin 80', 'substances' => [4 => 'Asam asetilsalisilat']];
    private const AMOX = ['id' => 14, 'name' => 'Amoxicillin 500', 'substances' => [5 => 'Amoksisilin']];
    private const IBU = ['id' => 15, 'name' => 'Ibuprofen 400', 'substances' => [6 => 'Ibuprofen']];

    private CdsEngine $engine;

    private array $rules;

    protected function setUp(): void
    {
        $this->engine = new CdsEngine;
        $this->rules = [
            '3-4' => ['id' => 100, 'severity' => Severity::Major, 'effect' => 'Risiko perdarahan.', 'recommendation' => 'Hindari.'],
            '4-6' => ['id' => 101, 'severity' => Severity::Moderate, 'effect' => 'Efek antiplatelet berkurang.', 'recommendation' => null],
            '1-2' => ['id' => 102, 'severity' => Severity::Minor, 'effect' => 'Kombinasi tetap.', 'recommendation' => null],
        ];
    }

    private function keys(array $alerts): array
    {
        return array_map(fn (ClinicalAlert $a) => $a->key, $alerts);
    }

    public function test_no_alerts_for_safe_prescription(): void
    {
        $this->assertSame([], $this->engine->check([self::PCT, self::AMOX], [], $this->rules));
    }

    public function test_allergy_detected_by_substance_not_brand(): void
    {
        $alerts = $this->engine->check([self::PCT_CAF], [['substance_id' => 1, 'name' => 'Parasetamol', 'reaction' => 'ruam']], []);

        $this->assertCount(1, $alerts);
        $this->assertSame(ClinicalAlert::ALLERGY, $alerts[0]->type);
        $this->assertSame(Severity::Major, $alerts[0]->severity);
        $this->assertTrue($alerts[0]->requiresOverride());
        $this->assertSame('allergy:1:11', $alerts[0]->key);
        $this->assertStringContainsString('reaksi: ruam', $alerts[0]->message);
    }

    public function test_interaction_is_symmetric(): void
    {
        $ab = $this->engine->check([self::WARF, self::ASA], [], $this->rules);
        $ba = $this->engine->check([self::ASA, self::WARF], [], $this->rules);

        $this->assertSame($this->keys($ab), $this->keys($ba));
        $this->assertSame('interaction:100:12-13', $ab[0]->key);
        $this->assertSame(Severity::Major, $ab[0]->severity);
        $this->assertSame([12, 13], $ab[0]->drugIds);
        $this->assertSame(100, $ab[0]->ruleId);
    }

    public function test_no_interaction_within_single_combination_drug(): void
    {
        // Parasetamol + kafein dalam SATU obat bukan interaksi yang perlu diperingatkan.
        $this->assertSame([], $this->engine->check([self::PCT_CAF], [], $this->rules));
    }

    public function test_duplicate_substance_across_drugs(): void
    {
        $alerts = $this->engine->check([self::PCT, self::PCT_CAF], [], $this->rules);

        $types = array_map(fn ($a) => $a->type, $alerts);
        $this->assertContains(ClinicalAlert::DUPLICATE, $types);

        $dup = $alerts[array_search(ClinicalAlert::DUPLICATE, $types, true)];
        $this->assertSame('duplicate:1:10-11', $dup->key);
        $this->assertSame(Severity::Moderate, $dup->severity);
        $this->assertFalse($dup->requiresOverride());
    }

    public function test_alerts_sorted_by_severity(): void
    {
        $alerts = $this->engine->check(
            [self::WARF, self::ASA, self::IBU, self::AMOX],
            [['substance_id' => 5, 'name' => 'Amoksisilin']],
            $this->rules,
        );

        $this->assertSame(
            ['major', 'major', 'moderate'],
            array_map(fn ($a) => $a->severity->value, $alerts),
        );
        $this->assertSame(['allergy:5:14', 'interaction:100:12-13', 'interaction:101:13-15'], $this->keys($alerts));
    }

    public function test_to_array_contains_ui_fields(): void
    {
        $alert = $this->engine->check([self::WARF, self::ASA], [], $this->rules)[0]->toArray();

        $this->assertSame('Interaksi', $alert['type_label']);
        $this->assertSame('Berat', $alert['severity_label']);
        $this->assertTrue($alert['requires_override']);
    }
}
