<?php

namespace App\Modules\Import\Importers;

use App\Modules\Facility\Models\MedicalService;
use App\Modules\Facility\Models\MedicalServicePrice;
use App\Modules\Facility\Models\Poli;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/** Master tindakan medis + tarif per jenis bayar. */
class ServiceImporter extends Importer
{
    use HasPriceColumns;

    /** @var array<string, int> kode poli (lowercase) => id */
    private array $polis = [];

    /** @var array<string, true> */
    private array $seen = [];

    public function type(): string
    {
        return 'services';
    }

    public function label(): string
    {
        return 'Tindakan medis';
    }

    public function ability(): string
    {
        return 'settings.manage';
    }

    public function description(): string
    {
        return 'Kosongkan kolom poli agar tindakan berlaku di semua poli.';
    }

    public function columns(): array
    {
        return [
            'kode' => [true, 'Kode unik tindakan', 'NEBU'],
            'nama' => [true, 'Nama tindakan', 'Nebulisasi'],
            'poli' => [false, 'Kode poli (kosong = semua poli)', null],
        ] + $this->priceColumns('tindakan', 60000);
    }

    public function prepare(): void
    {
        $this->polis = Poli::query()->get(['id', 'code'])->mapWithKeys(fn ($p) => [self::lower($p->code) => $p->id])->all();
        $this->seen = MedicalService::query()->pluck('code')->mapWithKeys(fn ($c) => [self::lower($c) => true])->all();
    }

    public function validate(array $row): array
    {
        $errors = [];
        $p = $this->parse($row, $errors);

        if ($p['code'] !== null) {
            if (isset($this->seen[self::lower($p['code'])])) {
                $errors[] = "Kode tindakan \"{$p['code']}\" sudah ada.";
            }
            $this->seen[self::lower($p['code'])] = true;
        }

        return $errors;
    }

    public function import(array $rows, User $user, string $sourceName): int
    {
        return DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $errors = [];
                $p = $this->parse($row, $errors);
                $service = MedicalService::create(['code' => $p['code'], 'name' => $p['name'], 'poli_id' => $p['poli_id'], 'is_active' => true]);
                foreach ($p['prices'] as $typeId => $price) {
                    MedicalServicePrice::create(['medical_service_id' => $service->id, 'payment_type_id' => $typeId, 'price' => $price]);
                }
            }

            return count($rows);
        });
    }

    private function parse(array $row, array &$errors): array
    {
        $code = $this->required($row, 'kode', 'Kode', $errors);
        $name = $this->required($row, 'nama', 'Nama', $errors);

        if ($code !== null && mb_strlen($code) > 20) {
            $errors[] = 'Kode maksimal 20 karakter.';
        }

        $poliCode = $this->str($row, 'poli');
        $poliId = $poliCode === null ? null : ($this->polis[self::lower($poliCode)] ?? null);
        if ($poliCode !== null && ! $poliId) {
            $errors[] = "Poli \"{$poliCode}\" tidak ditemukan.";
        }

        return ['code' => $code ? mb_strtoupper($code) : null, 'name' => $name, 'poli_id' => $poliId, 'prices' => $this->prices($row, $errors)];
    }
}
