<?php

namespace App\Modules\Inventory\Services;

use InvalidArgumentException;

/**
 * Konversi kemasan ↔ satuan terkecil. Semua stok & alokasi disimpan dalam
 * satuan terkecil (PRD §6.4), jadi seluruh perhitungan di sini integer.
 *
 * $packagings: daftar ['unit_name' => string, 'quantity_in_base' => int].
 */
class PackagingConverter
{
    /** 2 Strip @10 tablet → 20 tablet. */
    public function toBase(int $quantity, int $quantityInBase): int
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Jumlah tidak boleh negatif.');
        }
        if ($quantityInBase < 1) {
            throw new InvalidArgumentException('Isi kemasan minimal 1 satuan terkecil.');
        }
        if ($quantity > intdiv(PHP_INT_MAX, $quantityInBase)) {
            throw new InvalidArgumentException('Jumlah terlalu besar.');
        }

        return $quantity * $quantityInBase;
    }

    /** Konversi lewat nama kemasan, mis. ('Strip', 2) → 20. */
    public function toBaseByUnit(array $packagings, string $unitName, int $quantity): int
    {
        foreach ($packagings as $p) {
            if (strcasecmp($p['unit_name'], $unitName) === 0) {
                return $this->toBase($quantity, (int) $p['quantity_in_base']);
            }
        }

        throw new InvalidArgumentException("Kemasan \"{$unitName}\" tidak dikenal untuk obat ini.");
    }

    /**
     * Pecah satuan terkecil ke kemasan terbesar dulu.
     * 125 tablet (Box 100, Strip 10, Tablet 1) → Box 1, Strip 2, Tablet 5.
     *
     * @return list<array{unit_name: string, quantity: int}>
     */
    public function breakdown(int $baseQuantity, array $packagings): array
    {
        if ($baseQuantity < 0) {
            throw new InvalidArgumentException('Jumlah tidak boleh negatif.');
        }

        usort($packagings, fn ($a, $b) => $b['quantity_in_base'] <=> $a['quantity_in_base']);

        $result = [];
        $remaining = $baseQuantity;

        foreach ($packagings as $p) {
            $size = (int) $p['quantity_in_base'];
            if ($size < 1) {
                continue;
            }
            $count = intdiv($remaining, $size);
            if ($count > 0) {
                $result[] = ['unit_name' => $p['unit_name'], 'quantity' => $count];
                $remaining -= $count * $size;
            }
        }

        if ($remaining !== 0) {
            throw new InvalidArgumentException('Kemasan satuan terkecil (isi 1) tidak ditemukan.');
        }

        return $result;
    }

    /** "1 Box + 2 Strip + 5 Tablet (125 tablet)". */
    public function format(int $baseQuantity, array $packagings, string $baseUnit): string
    {
        if ($baseQuantity === 0) {
            return "0 {$baseUnit}";
        }

        $parts = array_map(
            fn ($row) => "{$row['quantity']} {$row['unit_name']}",
            $this->breakdown($baseQuantity, $packagings),
        );

        $label = implode(' + ', $parts);

        return count($parts) === 1 && strcasecmp(end($parts), "{$baseQuantity} {$baseUnit}") === 0
            ? $label
            : "{$label} ({$baseQuantity} {$baseUnit})";
    }

    /**
     * Validasi struktur kemasan sebelum disimpan.
     *
     * @return list<string> pesan kesalahan; kosong bila valid
     */
    public function validate(array $packagings): array
    {
        $errors = [];
        $names = [];
        $sizes = [];

        if (! $packagings) {
            return ['Minimal satu kemasan (satuan terkecil) wajib diisi.'];
        }

        foreach ($packagings as $i => $p) {
            $row = $i + 1;
            $name = trim((string) ($p['unit_name'] ?? ''));
            $size = $p['quantity_in_base'] ?? null;

            if ($name === '') {
                $errors[] = "Kemasan baris {$row}: nama kemasan wajib diisi.";
            }
            if (! is_numeric($size) || (int) $size != $size || (int) $size < 1) {
                $errors[] = "Kemasan baris {$row}: isi harus bilangan bulat ≥ 1.";

                continue;
            }

            $key = mb_strtolower($name);
            if ($name !== '' && isset($names[$key])) {
                $errors[] = "Kemasan \"{$name}\" tercantum lebih dari sekali.";
            }
            if (isset($sizes[(int) $size])) {
                $errors[] = "Dua kemasan memiliki isi yang sama ({$size}); isi tiap kemasan harus berbeda.";
            }
            $names[$key] = true;
            $sizes[(int) $size] = true;
        }

        if (! isset($sizes[1])) {
            $errors[] = 'Wajib ada kemasan satuan terkecil dengan isi 1.';
        }

        return $errors;
    }
}
