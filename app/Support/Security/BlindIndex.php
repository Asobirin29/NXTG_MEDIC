<?php

namespace App\Support\Security;

use RuntimeException;

/**
 * Hash HMAC-SHA256 satu arah untuk mencari kolom terenkripsi (NIK) tanpa
 * mendekripsi seluruh tabel (PRD §10.5). Nilai dinormalisasi lebih dulu
 * supaya "3201 2345..." dan "32012345..." menghasilkan index yang sama.
 */
class BlindIndex
{
    public function __construct(private string $key) {}

    public static function fromConfig(): self
    {
        $key = (string) config('medic.blind_index_key');

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true) ?: '';
        }

        if (strlen($key) < 32) {
            throw new RuntimeException('BLIND_INDEX_KEY belum diatur (minimal 32 byte).');
        }

        return new self($key);
    }

    public function nik(?string $nik): ?string
    {
        $normalized = self::normalizeNik($nik);

        return $normalized === null ? null : hash_hmac('sha256', 'nik:'.$normalized, $this->key);
    }

    public static function normalizeNik(?string $nik): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $nik);

        return $digits === '' ? null : $digits;
    }
}
