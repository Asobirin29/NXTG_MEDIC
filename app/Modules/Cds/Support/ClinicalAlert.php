<?php

namespace App\Modules\Cds\Support;

use App\Modules\Cds\Enums\Severity;

/** Satu peringatan klinis hasil pengecekan CDS. */
final class ClinicalAlert
{
    public const ALLERGY = 'allergy';
    public const INTERACTION = 'interaction';
    public const DUPLICATE = 'duplicate';

    /**
     * @param  list<int>  $drugIds  obat yang memicu peringatan
     */
    public function __construct(
        public readonly string $type,
        public readonly Severity $severity,
        public readonly string $key,
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $recommendation,
        public readonly array $drugIds,
        public readonly ?int $ruleId = null,
    ) {}

    /** Peringatan berat wajib dikonfirmasi dengan alasan tertulis sebelum proses berlanjut. */
    public function requiresOverride(): bool
    {
        return $this->severity === Severity::Major;
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::ALLERGY => 'Alergi',
            self::INTERACTION => 'Interaksi',
            self::DUPLICATE => 'Duplikasi zat aktif',
        };
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'type_label' => $this->typeLabel(),
            'severity' => $this->severity->value,
            'severity_label' => $this->severity->label(),
            'key' => $this->key,
            'title' => $this->title,
            'message' => $this->message,
            'recommendation' => $this->recommendation,
            'drug_ids' => $this->drugIds,
            'requires_override' => $this->requiresOverride(),
        ];
    }
}
