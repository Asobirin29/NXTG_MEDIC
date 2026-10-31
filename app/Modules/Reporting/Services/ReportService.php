<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Inventory\Enums\DrugCategory;
use App\Modules\Inventory\Models\Drug;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Pos\Models\Sale;
use App\Modules\Pos\Models\SaleItem;
use App\Modules\Pos\Models\SaleReturn;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Laporan Fase 1 (PRD §5): penjualan/pendapatan, pemakaian obat, dan obat
 * terbatas (psikotropika & narkotika). Semua angka obat dalam satuan
 * terkecil; transaksi void tidak dihitung, retur mengurangi.
 */
class ReportService
{
    /** @return array{days: Collection, payment_types: Collection, totals: array} */
    public function sales(Carbon $from, Carbon $to): array
    {
        $sales = Sale::with('paymentType')
            ->where('status', Sale::COMPLETED)
            ->whereBetween('completed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get();
        $refunds = SaleReturn::whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->get();

        $days = collect();
        for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) {
            $key = $d->toDateString();
            $daySales = $sales->filter(fn ($s) => $s->completed_at->toDateString() === $key);
            $days->push([
                'date' => $d->copy(),
                'count' => $daySales->count(),
                'otc' => $daySales->where('type', 'otc')->count(),
                'value' => (int) $daySales->sum('total_value'),
                'collected' => (int) $daySales->sum('amount_due'),
                'refund' => (int) $refunds->filter(fn ($r) => $r->created_at->toDateString() === $key)->sum('refund_amount'),
                'cost' => (float) $daySales->sum(fn ($s) => (float) $s->cost_total),
            ]);
        }

        $paymentTypes = $sales->groupBy('payment_type_id')->map(fn ($group) => [
            'name' => $group->first()->paymentType->name,
            'count' => $group->count(),
            'value' => (int) $group->sum('total_value'),
            'collected' => (int) $group->sum('amount_due'),
        ])->sortByDesc('value')->values();

        return [
            'days' => $days,
            'payment_types' => $paymentTypes,
            'totals' => [
                'count' => $sales->count(),
                'value' => (int) $sales->sum('total_value'),
                'collected' => (int) $sales->sum('amount_due'),
                'refund' => (int) $refunds->sum('refund_amount'),
                'cost' => (float) $sales->sum(fn ($s) => (float) $s->cost_total),
                'voided' => Sale::where('status', Sale::VOIDED)->whereBetween('voided_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count(),
            ],
        ];
    }

    /** Pemakaian obat per item: jumlah keluar bersih (dikurangi retur), nilai, dan HPP. */
    public function usage(Carbon $from, Carbon $to): Collection
    {
        return SaleItem::with('drug.packagings')
            ->where('kind', 'drug')
            ->whereHas('sale', fn ($q) => $q->where('status', Sale::COMPLETED)->whereBetween('completed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]))
            ->get()
            ->groupBy('drug_id')
            ->map(function (Collection $items) {
                $drug = $items->first()->drug;

                return [
                    'drug' => $drug,
                    'quantity' => (int) $items->sum(fn ($i) => $i->quantity_base - $i->returned_base),
                    'returned' => (int) $items->sum('returned_base'),
                    'value' => (int) $items->sum(fn ($i) => $i->line_total - $i->returned_base * $i->unit_price),
                    'cost' => (float) $items->sum(fn ($i) => (float) $i->cost_total),
                    'transactions' => $items->pluck('sale_id')->unique()->count(),
                ];
            })
            ->sortByDesc('quantity')
            ->values();
    }

    /**
     * Mutasi psikotropika & narkotika satu periode (bahan pelaporan SIPNAP):
     * stok awal, penerimaan, pengeluaran pelayanan (bersih dari void/retur),
     * koreksi (penyesuaian/opname), stok akhir — dari buku besar stok.
     */
    public function restricted(Carbon $from, Carbon $to): Collection
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();
        $drugs = Drug::with('packagings')
            ->whereIn('category', [DrugCategory::Psikotropika->value, DrugCategory::Narkotika->value])
            ->orderBy('category')->orderBy('name')->get();

        $opening = StockMovement::whereIn('drug_id', $drugs->pluck('id'))->where('created_at', '<', $start)
            ->groupBy('drug_id')->selectRaw('drug_id, SUM(quantity) as qty')->pluck('qty', 'drug_id');
        $period = StockMovement::whereIn('drug_id', $drugs->pluck('id'))->whereBetween('created_at', [$start, $end])
            ->groupBy('drug_id', 'type')->selectRaw('drug_id, type, SUM(quantity) as qty')->get()
            ->groupBy('drug_id');

        return $drugs->map(function (Drug $drug) use ($opening, $period) {
            $byType = ($period[$drug->id] ?? collect())->mapWithKeys(fn ($m) => [$m->type->value => (int) $m->qty]);
            $open = (int) ($opening[$drug->id] ?? 0);
            $in = $byType['receipt'] ?? 0;
            $out = -(($byType['dispense'] ?? 0) + ($byType['void'] ?? 0) + ($byType['return'] ?? 0) + ($byType['quarantine'] ?? 0));
            $correction = ($byType['adjustment'] ?? 0) + ($byType['opname'] ?? 0);

            return [
                'drug' => $drug,
                'opening' => $open,
                'in' => $in,
                'out' => $out,
                'correction' => $correction,
                'closing' => $open + $in - $out + $correction,
            ];
        });
    }
}
