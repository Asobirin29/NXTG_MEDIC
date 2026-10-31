<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Drug;
use App\Modules\Inventory\Models\DrugPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Simpan master obat beserta komposisi zat aktif, struktur kemasan, dan harga. */
class DrugService
{
    public function __construct(private PackagingConverter $converter) {}

    /**
     * @param  list<array{substance_id: int, strength_value?: ?string, strength_unit?: ?string}>  $substances
     * @param  list<array{id?: ?int, unit_name: string, quantity_in_base: int, barcode?: ?string, is_default_sale?: bool}>  $packagings
     * @param  array<int, int|string|null>  $prices  payment_type_id => harga per satuan terkecil (kosong = hapus)
     */
    public function save(Drug $drug, array $attributes, array $substances, array $packagings, array $prices): Drug
    {
        $packagings = array_values(array_filter($packagings, fn ($p) => filled($p['unit_name'] ?? null) || filled($p['quantity_in_base'] ?? null)));

        if ($errors = $this->converter->validate($packagings)) {
            throw ValidationException::withMessages(['packagings' => $errors]);
        }

        return DB::transaction(function () use ($drug, $attributes, $substances, $packagings, $prices) {
            $drug->fill($attributes)->save();

            $drug->substances()->sync(collect($substances)->mapWithKeys(fn ($s) => [
                (int) $s['substance_id'] => [
                    'strength_value' => filled($s['strength_value'] ?? null) ? $s['strength_value'] : null,
                    'strength_unit' => filled($s['strength_unit'] ?? null) ? $s['strength_unit'] : null,
                ],
            ])->all());

            $this->syncPackagings($drug, $packagings);
            $this->syncPrices($drug, $prices);

            return $drug->load(['substances', 'packagings', 'prices']);
        });
    }

    private function syncPackagings(Drug $drug, array $rows): void
    {
        $default = collect($rows)->search(fn ($p) => ! empty($p['is_default_sale']));
        if ($default === false) {
            $default = collect($rows)->search(fn ($p) => (int) $p['quantity_in_base'] === 1);
        }

        // Hapus dulu yang dibuang agar unique(drug_id, unit_name) tidak bentrok saat nama dipakai ulang.
        $keep = collect($rows)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        $drug->packagings()->whereNotIn('id', $keep)->get()->each->delete();

        foreach ($rows as $i => $row) {
            $values = [
                'unit_name' => trim($row['unit_name']),
                'quantity_in_base' => (int) $row['quantity_in_base'],
                'barcode' => filled($row['barcode'] ?? null) ? trim($row['barcode']) : null,
                'is_default_sale' => $i === $default,
            ];

            $existing = filled($row['id'] ?? null) ? $drug->packagings()->find($row['id']) : null;
            $existing ? $existing->update($values) : $drug->packagings()->create($values);
        }
    }

    private function syncPrices(Drug $drug, array $prices): void
    {
        foreach ($prices as $paymentTypeId => $price) {
            $current = DrugPrice::where('drug_id', $drug->id)->where('payment_type_id', $paymentTypeId)->first();

            if (blank($price)) {
                $current?->delete();

                continue;
            }

            if ($current) {
                $current->update(['price_per_base' => (int) $price]);
            } else {
                DrugPrice::create(['drug_id' => $drug->id, 'payment_type_id' => (int) $paymentTypeId, 'price_per_base' => (int) $price]);
            }
        }
    }
}
