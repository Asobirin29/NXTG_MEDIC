<?php

namespace App\Modules\Import\Importers;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\DrugCategory;
use App\Modules\Inventory\Models\Drug;
use App\Modules\Inventory\Models\Substance;
use App\Modules\Inventory\Services\DrugService;
use App\Modules\Inventory\Services\PackagingConverter;
use Illuminate\Support\Facades\DB;

/** Master obat: komposisi zat aktif, kemasan, golongan, harga per jenis bayar. */
class DrugImporter extends Importer
{
    use HasPriceColumns;

    /** @var array<string, int> nama zat aktif (lowercase) => id */
    private array $substances = [];

    /** @var array<string, true> */
    private array $seen = [];

    public function __construct(private DrugService $drugs, private PackagingConverter $converter) {}

    public function type(): string
    {
        return 'drugs';
    }

    public function label(): string
    {
        return 'Master obat';
    }

    public function ability(): string
    {
        return 'inventory.manage';
    }

    public function description(): string
    {
        return 'Zat aktif harus sudah ada di menu Zat Aktif (dicocokkan persis) agar cek alergi & interaksi bekerja. Pisahkan beberapa zat dengan "+", kemasan dengan ";".';
    }

    public function columns(): array
    {
        return [
            'nama' => [true, 'Nama dagang/generik + kekuatan', 'Paracetamol 500 mg'],
            'kode_kfa' => [false, 'Kode KFA Kemenkes', null],
            'bentuk_sediaan' => [true, implode(', ', Drug::DOSAGE_FORMS), 'tablet'],
            'kekuatan' => [false, 'Teks bebas', '500 mg'],
            'golongan' => [true, 'bebas, bebas terbatas, keras, psikotropika, narkotika', 'bebas'],
            'satuan_terkecil' => [true, implode(', ', Drug::BASE_UNITS), 'tablet'],
            'zat_aktif' => [true, 'Zat [kekuatan satuan] + zat lain', 'Parasetamol 500 mg'],
            'kemasan' => [false, 'Kemasan=isi satuan terkecil; …', 'Strip=10; Box=100'],
            'kemasan_jual' => [false, 'Kemasan default saat dijual', 'Strip'],
            'stok_minimum' => [false, 'Ambang stok menipis (satuan terkecil)', 100],
            'barcode' => [false, 'Barcode obat', null],
        ] + $this->priceColumns('satuan terkecil', 300);
    }

    public function prepare(): void
    {
        $this->substances = Substance::query()->get(['id', 'name'])->mapWithKeys(fn ($s) => [self::lower($s->name) => $s->id])->all();
        $this->seen = [];
        foreach (Drug::query()->get(['name', 'kfa_code', 'barcode']) as $d) {
            $this->seen['name:'.self::lower($d->name)] = true;
            if ($d->kfa_code) {
                $this->seen['kfa:'.$d->kfa_code] = true;
            }
            if ($d->barcode) {
                $this->seen['barcode:'.$d->barcode] = true;
            }
        }
    }

    public function validate(array $row): array
    {
        $errors = [];
        $parsed = $this->parse($row, $errors);

        foreach (['name' => 'Nama obat', 'kfa' => 'Kode KFA', 'barcode' => 'Barcode'] as $key => $label) {
            $value = $key === 'name' ? self::lower($parsed['attributes']['name'] ?? null) : ($key === 'kfa' ? $parsed['attributes']['kfa_code'] ?? null : $parsed['attributes']['barcode'] ?? null);
            if ($value && isset($this->seen["{$key}:{$value}"])) {
                $errors[] = "{$label} \"{$value}\" sudah ada di master obat atau di baris sebelumnya.";
            }
        }

        if (! $errors) {
            $this->seen['name:'.self::lower($parsed['attributes']['name'])] = true;
            if ($parsed['attributes']['kfa_code']) {
                $this->seen['kfa:'.$parsed['attributes']['kfa_code']] = true;
            }
            if ($parsed['attributes']['barcode']) {
                $this->seen['barcode:'.$parsed['attributes']['barcode']] = true;
            }
        }

        return $errors;
    }

    public function import(array $rows, User $user, string $sourceName): int
    {
        return DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $errors = [];
                $p = $this->parse($row, $errors);
                $this->drugs->save(new Drug, $p['attributes'], $p['substances'], $p['packagings'], $p['prices']);
            }

            return count($rows);
        });
    }

    /** @return array{attributes: array, substances: list<array>, packagings: list<array>, prices: array<int, int>} */
    private function parse(array $row, array &$errors): array
    {
        $name = $this->required($row, 'nama', 'Nama', $errors);
        $form = self::lower($this->required($row, 'bentuk_sediaan', 'Bentuk sediaan', $errors));
        $base = self::lower($this->required($row, 'satuan_terkecil', 'Satuan terkecil', $errors));
        $categoryText = $this->required($row, 'golongan', 'Golongan', $errors);

        if ($form !== '' && ! in_array($form, Drug::DOSAGE_FORMS, true)) {
            $errors[] = "Bentuk sediaan \"{$form}\" tidak dikenal.";
        }
        if ($base !== '' && ! in_array($base, Drug::BASE_UNITS, true)) {
            $errors[] = "Satuan terkecil \"{$base}\" tidak dikenal.";
        }

        $category = null;
        if ($categoryText !== null) {
            $key = str_replace([' ', '-'], '_', preg_replace('/^obat\s+/', '', self::lower($categoryText)));
            $category = DrugCategory::tryFrom($key);
            if (! $category) {
                $errors[] = "Golongan \"{$categoryText}\" tidak dikenal.";
            }
        }

        $min = $this->int($row, 'stok_minimum');
        if ($min === false || (is_int($min) && $min < 0)) {
            $errors[] = 'Stok minimum harus angka bulat ≥ 0.';
        }

        // Zat aktif: "Parasetamol 500 mg + Kafein 50 mg"
        $substances = [];
        $composition = $this->required($row, 'zat_aktif', 'Zat aktif', $errors);
        foreach ($composition ? array_filter(array_map('trim', explode('+', $composition))) : [] as $part) {
            $value = $unit = null;
            $subName = $part;
            if (preg_match('/^(.*?)\s+([\d]+(?:[.,]\d+)?)\s*([^\s\d]+)$/u', $part, $m)) {
                [$subName, $value, $unit] = [trim($m[1]), (float) str_replace(',', '.', $m[2]), $m[3]];
            }
            $id = $this->substances[self::lower($subName)] ?? null;
            if (! $id) {
                $errors[] = "Zat aktif \"{$subName}\" belum ada di daftar Zat Aktif.";

                continue;
            }
            $substances[] = ['substance_id' => $id, 'strength_value' => $value, 'strength_unit' => $unit];
        }
        if (count(array_unique(array_column($substances, 'substance_id'))) !== count($substances)) {
            $errors[] = 'Zat aktif tercantum lebih dari sekali.';
        }

        // Kemasan: satuan terkecil (isi 1) selalu ada; tambahan "Strip=10; Box=100".
        $baseLabel = $base === 'ml' ? 'ml' : ucfirst($base);
        $saleUnit = self::lower($this->str($row, 'kemasan_jual'));
        $packagings = [['unit_name' => $baseLabel, 'quantity_in_base' => 1]];
        foreach (array_filter(array_map('trim', explode(';', (string) $this->str($row, 'kemasan')))) as $pack) {
            if (! preg_match('/^(.+?)\s*[=:]\s*(\d+)$/u', $pack, $m)) {
                $errors[] = "Format kemasan \"{$pack}\" salah; contoh: Strip=10.";

                continue;
            }
            if ((int) $m[2] !== 1) {
                $packagings[] = ['unit_name' => trim($m[1]), 'quantity_in_base' => (int) $m[2]];
            }
        }
        foreach ($this->converter->validate($packagings) as $message) {
            $errors[] = $message;
        }
        $packagings = array_map(fn ($p) => $p + ['is_default_sale' => $saleUnit !== '' && self::lower($p['unit_name']) === $saleUnit], $packagings);
        if ($saleUnit !== '' && ! in_array(true, array_column($packagings, 'is_default_sale'), true)) {
            $errors[] = "Kemasan jual \"{$saleUnit}\" tidak ada di daftar kemasan.";
        }

        return [
            'attributes' => [
                'name' => $name, 'kfa_code' => $this->str($row, 'kode_kfa'), 'dosage_form' => $form, 'strength' => $this->str($row, 'kekuatan'),
                'category' => $category, 'base_unit' => $base, 'barcode' => $this->str($row, 'barcode'),
                'min_stock' => is_int($min) ? $min : 0, 'is_active' => true,
            ],
            'substances' => $substances,
            'packagings' => $packagings,
            'prices' => $this->prices($row, $errors),
        ];
    }
}
