<?php

namespace App\Modules\Import\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Baca/tulis lembar Excel (.xlsx) dan CSV. Baris pertama = judul kolom;
 * judul dinormalisasi ("Tanggal Lahir*" → "tanggal_lahir") agar templat
 * yang diketik ulang pengguna tetap terbaca.
 */
class Spreadsheet
{
    public const MAX_ROWS = 5000;

    /**
     * @return \Generator<int, array<string, mixed>> nomor baris di file => kolom
     */
    public static function rows(string $path, string $extension): \Generator
    {
        $reader = match (strtolower($extension)) {
            'xlsx' => new XlsxReader,
            'csv', 'txt' => new CsvReader(self::csvOptions($path)),
            default => throw new InvalidArgumentException('Format file harus .xlsx atau .csv.'),
        };

        $reader->open($path);
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $headers = null;
                $line = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    $line++;
                    $cells = array_map([self::class, 'cell'], self::values($row));

                    if ($headers === null) {
                        if (array_filter($cells, fn ($c) => $c !== null) === []) {
                            continue;
                        }
                        $headers = array_map([self::class, 'normalizeHeader'], $cells);

                        continue;
                    }
                    if (array_filter($cells, fn ($c) => $c !== null) === []) {
                        continue; // baris kosong
                    }

                    $assoc = [];
                    foreach ($headers as $i => $key) {
                        if ($key !== '') {
                            $assoc[$key] = $cells[$i] ?? null;
                        }
                    }
                    yield $line => $assoc;
                }
                break; // hanya lembar pertama
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<list<mixed>>  $rows
     */
    public static function writeXlsx(string $path, array $headers, iterable $rows): void
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_values($row)));
        }
        $writer->close();
    }

    public static function normalizeHeader(mixed $value): string
    {
        $text = mb_strtolower(trim((string) $value));
        $text = preg_replace('/\(.*?\)|\*/', '', $text);

        return trim(preg_replace('/[^a-z0-9]+/', '_', $text), '_');
    }

    /** Sel kosong → null; tanggal Excel → "Y-m-d"; teks di-trim. */
    private static function cell(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }

    private static function values(Row $row): array
    {
        return $row->toArray();
    }

    /** CSV dari Excel berbahasa Indonesia biasanya memakai titik koma. */
    private static function csvOptions(string $path): CsvOptions
    {
        $options = new CsvOptions;
        $handle = fopen($path, 'r');
        $first = (string) fgets($handle);
        fclose($handle);
        $options->FIELD_DELIMITER = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';

        return $options;
    }
}
