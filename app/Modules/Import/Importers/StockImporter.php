<?php

namespace App\Modules\Import\Importers;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Drug;
use App\Modules\Inventory\Models\Supplier;
use App\Modules\Inventory\Services\GoodsReceiptService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stok awal per batch. Dicatat sebagai penerimaan barang (satu dokumen per
 * pemasok) sehingga kartu stok punya asal-usul yang jelas.
 */
class StockImporter extends Importer
{
    public const DEFAULT_SUPPLIER = 'Saldo Awal (Impor)';

    private Collection $drugs;

    /** @var array<string, string> "drug_id|BATCH" => ED (Y-m-d) yang sudah tercatat */
    private array $batches = [];

    /** @var array<string, true> */
    private array $seen = [];

    public function __construct(private GoodsReceiptService $receipts) {}

    public function type(): string
    {
        return 'stock';
    }

    public function label(): string
    {
        return 'Stok awal per batch';
    }

    public function ability(): string
    {
        return 'inventory.manage';
    }

    public function description(): string
    {
        return 'Obat dicocokkan lewat nama persis, kode KFA, atau barcode. Batch kedaluwarsa tidak diterima. Batch yang sudah ada dengan ED sama akan ditambah saldonya.';
    }

    public function columns(): array
    {
        return [
            'obat' => [true, 'Nama obat persis / kode KFA / barcode', 'Paracetamol 500 mg'],
            'kemasan' => [false, 'Nama kemasan (default: satuan terkecil)', 'Box'],
            'jumlah' => [true, 'Jumlah dalam kemasan tsb.', 5],
            'no_batch' => [true, 'Nomor batch', 'PCT2611A'],
            'ed' => [true, 'Tanggal kedaluwarsa (dd/mm/yyyy)', today()->addYear()->format('d/m/Y')],
            'harga_beli' => [false, 'Harga beli per kemasan (Rupiah)', 18000],
            'pemasok' => [false, 'Nama pemasok (dibuat bila belum ada)', 'PBF Sinar Medika'],
        ];
    }

    public function prepare(): void
    {
        $this->drugs = Drug::with('packagings')->get();
        $this->batches = Batch::where('status', Batch::STATUS_AVAILABLE)->get(['drug_id', 'batch_number', 'expiry_date'])
            ->mapWithKeys(fn ($b) => ["{$b->drug_id}|{$b->batch_number}" => $b->expiry_date->toDateString()])->all();
        $this->seen = [];
    }

    public function validate(array $row): array
    {
        $errors = [];
        $p = $this->parse($row, $errors);

        if (! $errors) {
            $key = "{$p['drug']->id}|{$p['batch_number']}";
            if (isset($this->seen[$key])) {
                $errors[] = "Batch {$p['batch_number']} {$p['drug']->name} muncul lebih dari sekali di file; gabungkan jumlahnya.";
            } elseif (isset($this->batches[$key]) && $this->batches[$key] !== $p['expiry_date']) {
                $errors[] = "Batch {$p['batch_number']} sudah tercatat dengan ED ".date('d/m/Y', strtotime($this->batches[$key])).'.';
            }
            $this->seen[$key] = true;
        }

        return $errors;
    }

    public function import(array $rows, User $user, string $sourceName): int
    {
        $groups = [];
        foreach ($rows as $row) {
            $errors = [];
            $p = $this->parse($row, $errors);
            $groups[$p['supplier']][] = [
                'drug_id' => $p['drug']->id, 'drug_packaging_id' => $p['packaging']->id, 'quantity' => $p['quantity'],
                'batch_number' => $p['batch_number'], 'expiry_date' => $p['expiry_date'], 'unit_price' => $p['unit_price'],
            ];
        }

        return DB::transaction(function () use ($groups, $user, $sourceName) {
            $count = 0;
            foreach ($groups as $supplierName => $items) {
                $supplier = Supplier::firstOrCreate(['name' => $supplierName], ['is_active' => true]);
                $this->receipts->receive(
                    ['supplier_id' => $supplier->id, 'received_at' => today()->toDateString(), 'notes' => "Impor stok awal dari {$sourceName}"],
                    $items, $user,
                );
                $count += count($items);
            }

            return $count;
        });
    }

    private function parse(array $row, array &$errors): array
    {
        $ref = $this->required($row, 'obat', 'Obat', $errors);
        $drug = $ref === null ? null : $this->drugs->first(fn (Drug $d) => self::lower($d->name) === self::lower($ref) || ($d->kfa_code && $d->kfa_code === $ref) || ($d->barcode && $d->barcode === $ref));
        if ($ref !== null && ! $drug) {
            $errors[] = "Obat \"{$ref}\" tidak ditemukan di master obat.";
        }

        $packaging = null;
        if ($drug) {
            $unit = $this->str($row, 'kemasan');
            $packaging = $unit === null
                ? $drug->packagings->firstWhere('quantity_in_base', 1)
                : $drug->packagings->first(fn ($p) => self::lower($p->unit_name) === self::lower($unit));
            if (! $packaging) {
                $errors[] = "Kemasan \"{$unit}\" tidak ada untuk {$drug->name} (tersedia: {$drug->packagings->pluck('unit_name')->implode(', ')}).";
            }
        }

        $quantity = $this->int($row, 'jumlah');
        if (! is_int($quantity) || $quantity < 1) {
            $errors[] = 'Jumlah harus angka bulat ≥ 1.';
        }

        $batch = $this->required($row, 'no_batch', 'Nomor batch', $errors);

        $expiry = $this->date($row, 'ed');
        if ($expiry === null) {
            $errors[] = 'Tanggal ED wajib diisi.';
        } elseif ($expiry === false) {
            $errors[] = 'Tanggal ED tidak terbaca; gunakan format dd/mm/yyyy.';
        } elseif ($expiry->lte(today())) {
            $errors[] = 'Batch sudah kedaluwarsa; tidak diimpor sebagai stok layak jual.';
        }

        $price = $this->int($row, 'harga_beli');
        if ($price === false || (is_int($price) && $price < 0)) {
            $errors[] = 'Harga beli harus angka bulat ≥ 0.';
        }

        return [
            'drug' => $drug,
            'packaging' => $packaging,
            'quantity' => is_int($quantity) ? $quantity : 0,
            'batch_number' => mb_strtoupper((string) $batch),
            'expiry_date' => $expiry instanceof \DateTimeInterface ? $expiry->toDateString() : null,
            'unit_price' => is_int($price) ? $price : 0,
            'supplier' => $this->str($row, 'pemasok') ?? self::DEFAULT_SUPPLIER,
        ];
    }
}
