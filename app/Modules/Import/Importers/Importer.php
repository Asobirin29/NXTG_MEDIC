<?php

namespace App\Modules\Import\Importers;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Carbon;

/**
 * Satu jenis impor. Alur: prepare() memuat data pembanding, validate()
 * dipanggil berurutan untuk setiap baris (boleh mengingat isi baris
 * sebelumnya untuk mendeteksi duplikat di dalam file), lalu import()
 * menyimpan baris yang lolos dalam satu transaksi.
 */
abstract class Importer
{
    abstract public function type(): string;

    abstract public function label(): string;

    /** Gate yang wajib dimiliki pengunggah. */
    abstract public function ability(): string;

    /**
     * Kolom templat: nama => [wajib?, keterangan, contoh].
     *
     * @return array<string, array{0: bool, 1: string, 2: mixed}>
     */
    abstract public function columns(): array;

    public function prepare(): void {}

    /** @return list<string> pesan kesalahan; kosong bila baris valid */
    abstract public function validate(array $row): array;

    /**
     * @param  array<int, array<string, mixed>>  $rows  baris valid (nomor baris => kolom)
     * @return int jumlah baris tersimpan
     */
    abstract public function import(array $rows, User $user, string $sourceName): int;

    /** Baris yang ditampilkan di pratinjau (disimpan di database): data sensitif disamarkan. */
    public function previewRow(array $row): array
    {
        return $row;
    }

    /** Catatan tambahan untuk pengguna di halaman impor. */
    public function description(): string
    {
        return '';
    }

    /** @return list<string> kolom wajib yang tidak ada di judul file */
    public function missingColumns(array $headers): array
    {
        return array_values(array_diff(
            array_keys(array_filter($this->columns(), fn ($c) => $c[0])),
            $headers,
        ));
    }

    protected function str(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        return $value === null ? null : (trim((string) $value) === '' ? null : trim((string) $value));
    }

    /** "1.500", "Rp 1.500", 1500.0 → 1500. null bila kosong, false bila bukan angka bulat. */
    protected function int(array $row, string $key): int|false|null
    {
        $value = $row[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return floor($value) == $value ? (int) $value : false;
        }

        $clean = preg_replace('/^rp\.?\s*/i', '', trim((string) $value));
        $clean = str_replace([' ', '.'], '', $clean);

        return preg_match('/^-?\d+$/', $clean) ? (int) $clean : false;
    }

    /** Tanggal: Y-m-d, d/m/Y, d-m-Y, atau nomor seri Excel. null bila kosong, false bila tidak terbaca. */
    protected function date(array $row, string $key): Carbon|false|null
    {
        $value = $row[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value) && $value > 20000 && $value < 80000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value);
        }

        $text = trim((string) $value);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $text, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/', $text, $m)) {
            [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return false;
        }

        return checkdate($mo, $d, $y) ? Carbon::create($y, $mo, $d)->startOfDay() : false;
    }

    protected function required(array $row, string $key, string $label, array &$errors): ?string
    {
        $value = $this->str($row, $key);
        if ($value === null) {
            $errors[] = "{$label} wajib diisi.";
        }

        return $value;
    }

    protected static function lower(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }
}
