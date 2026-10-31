<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Import\Support\Spreadsheet;
use App\Modules\Reporting\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Laporan dengan filter periode dan unduhan Excel (?unduh=1). */
class ReportController extends Controller
{
    public const REPORTS = [
        'penjualan' => ['Penjualan & pendapatan', 'bi-cash-stack', 'Transaksi lunas per hari dan per jenis bayar, retur, dan HPP.'],
        'pemakaian' => ['Pemakaian obat', 'bi-capsule', 'Jumlah obat keluar per item (resep + OTC), bersih dari retur.'],
        'obat-terbatas' => ['Obat terbatas', 'bi-shield-lock', 'Mutasi psikotropika & narkotika: stok awal, masuk, keluar, stok akhir.'],
    ];

    public function index(): View
    {
        return view('reports.index', ['reports' => self::REPORTS]);
    }

    public function show(Request $request, string $report, ReportService $service): View|BinaryFileResponse
    {
        abort_unless(isset(self::REPORTS[$report]), 404);
        [$from, $to] = $this->period($request, $report);

        $data = match ($report) {
            'penjualan' => $service->sales($from, $to),
            'pemakaian' => ['rows' => $service->usage($from, $to)],
            'obat-terbatas' => ['rows' => $service->restricted($from, $to)],
        };

        if ($request->boolean('unduh')) {
            return $this->export($report, $data, $from, $to);
        }

        return view('reports.'.$report, $data + [
            'title' => self::REPORTS[$report][0],
            'report' => $report,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /** Default: bulan berjalan. Rentang dibatasi 1 tahun agar laporan tetap ringan. */
    private function period(Request $request, string $report): array
    {
        $request->validate(['dari' => ['nullable', 'date'], 'sampai' => ['nullable', 'date', 'after_or_equal:dari']], [], ['dari' => 'tanggal awal', 'sampai' => 'tanggal akhir']);

        $from = $request->date('dari') ?? today()->startOfMonth();
        $to = $request->date('sampai') ?? today();
        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subYear();
        }

        return [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->startOfDay()];
    }

    private function export(string $report, array $data, Carbon $from, Carbon $to): BinaryFileResponse
    {
        [$headers, $rows] = match ($report) {
            'penjualan' => [
                ['Tanggal', 'Transaksi', 'OTC', 'Nilai layanan', 'Diterima dari pasien', 'Retur', 'HPP'],
                $data['days']->map(fn ($d) => [$d['date']->format('d/m/Y'), $d['count'], $d['otc'], $d['value'], $d['collected'], $d['refund'], round($d['cost'])]),
            ],
            'pemakaian' => [
                ['Obat', 'Golongan', 'Satuan', 'Jumlah keluar', 'Diretur', 'Nilai', 'HPP', 'Transaksi'],
                $data['rows']->map(fn ($r) => [$r['drug']->name, $r['drug']->category->label(), $r['drug']->base_unit, $r['quantity'], $r['returned'], $r['value'], round($r['cost']), $r['transactions']]),
            ],
            'obat-terbatas' => [
                ['Obat', 'Golongan', 'Satuan', 'Stok awal', 'Penerimaan', 'Pengeluaran', 'Koreksi', 'Stok akhir'],
                $data['rows']->map(fn ($r) => [$r['drug']->name, $r['drug']->category->label(), $r['drug']->base_unit, $r['opening'], $r['in'], $r['out'], $r['correction'], $r['closing']]),
            ],
        };

        $path = tempnam(sys_get_temp_dir(), 'rpt').'.xlsx';
        Spreadsheet::writeXlsx($path, $headers, $rows);

        return response()->download($path, "laporan-{$report}-{$from->format('Ymd')}-{$to->format('Ymd')}.xlsx")->deleteFileAfterSend();
    }
}
