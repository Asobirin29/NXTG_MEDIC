<?php

namespace App\Modules\Import\Importers;

use App\Modules\Facility\Models\PaymentType;
use Illuminate\Support\Collection;

/** Kolom harga per jenis bayar: harga_umum, harga_bpjs, … (mengikuti kode jenis bayar faskes). */
trait HasPriceColumns
{
    private ?Collection $paymentTypes = null;

    protected function paymentTypes(): Collection
    {
        return $this->paymentTypes ??= PaymentType::active()->ordered()->get();
    }

    protected function priceColumn(PaymentType $type): string
    {
        return 'harga_'.mb_strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $type->code));
    }

    /** @return array<string, array{0: bool, 1: string, 2: mixed}> */
    protected function priceColumns(string $unit, int $example): array
    {
        return $this->paymentTypes()->mapWithKeys(fn (PaymentType $t) => [
            $this->priceColumn($t) => [false, "Harga {$t->name} per {$unit} (Rupiah)", $t->patient_pays ? $example : (int) round($example * 0.8)],
        ])->all();
    }

    /**
     * @return array<int, int> payment_type_id => harga
     */
    protected function prices(array $row, array &$errors): array
    {
        $prices = [];
        foreach ($this->paymentTypes() as $type) {
            $value = $this->int($row, $this->priceColumn($type));
            if ($value === false || (is_int($value) && $value < 0)) {
                $errors[] = "Harga {$type->name} harus angka bulat ≥ 0.";
            } elseif ($value !== null) {
                $prices[$type->id] = $value;
            }
        }

        return $prices;
    }
}
