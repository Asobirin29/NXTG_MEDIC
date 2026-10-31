<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Enums\Role;
use App\Modules\Inventory\Services\StockLevels;
use App\Modules\Patient\Enums\EncounterStatus;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Pos\Models\Sale;
use App\Support\Navigation\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Beranda per role (PRD §11.1): angka hari ini, antrean yang menunggu peran ini, peringatan. */
class DashboardController extends Controller
{
    public function __invoke(Request $request, StockLevels $stock): View
    {
        $user = $request->user();

        // Antrean hari ini per status — dasar kartu semua role.
        $today = Encounter::whereDate('queue_date', today())->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
        $count = fn (EncounterStatus ...$s) => (int) collect($s)->sum(fn ($x) => $today[$x->value] ?? 0);
        $visits = [
            'total' => (int) $today->sum() - $count(EncounterStatus::Cancelled),
            'waiting_nurse' => $count(EncounterStatus::Queued),
            'waiting_doctor' => $count(EncounterStatus::NurseAssessed),
            'in_consultation' => $count(EncounterStatus::InConsultation),
            'done' => $count(EncounterStatus::Completed),
        ];

        // Apoteker: stok menipis & obat mendekati kedaluwarsa langsung di beranda.
        $stockAlerts = null;
        if ($user->can('inventory.view')) {
            $low = $stock->lowStock();
            $expiring = $stock->nearExpiry();
            $expired = $stock->expired();
            $stockAlerts = [
                'counts' => ['low' => $low->count(), 'expiring' => $expiring->count(), 'expired' => $expired->count()],
                'low' => $low->take(5),
                'expiring' => $expired->concat($expiring)->take(5),
            ];
        }

        // Antrean farmasi & kasir: resep baru dari dokter dan pasien siap bayar.
        $posQueue = $user->can('pos.access') ? [
            'rx' => Encounter::where('status', EncounterStatus::WaitingPharmacy)->count(),
            'billing' => Encounter::where('status', EncounterStatus::ReadyBilling)->count(),
            'collected' => (int) Sale::where('status', Sale::COMPLETED)->whereDate('completed_at', today())->sum('amount_due'),
        ] : null;

        return view('dashboard', [
            'user' => $user,
            'tenant' => $user->tenant,
            'visits' => $visits,
            'posQueue' => $posQueue,
            'stockAlerts' => $stockAlerts,
            'trend' => $user->hasRole(Role::Admin) ? $this->trend() : null,
            'shortcuts' => (new Menu($user))->sections(),
        ]);
    }

    /** 14 hari terakhir: kunjungan (tanpa batal) dan uang diterima, untuk admin. */
    private function trend(): array
    {
        $from = today()->subDays(13);
        $visits = Encounter::whereDate('queue_date', '>=', $from)->where('status', '!=', EncounterStatus::Cancelled->value)
            ->selectRaw('DATE(queue_date) as d, COUNT(*) as n')->groupBy('d')->pluck('n', 'd');
        $revenue = Sale::where('status', Sale::COMPLETED)->whereDate('completed_at', '>=', $from)
            ->selectRaw('DATE(completed_at) as d, SUM(amount_due) as v')->groupBy('d')->pluck('v', 'd');

        $points = fn ($series) => collect(range(0, 13))->map(function ($i) use ($from, $series) {
            $day = $from->copy()->addDays($i);

            return ['label' => $day->format('j'), 'value' => (int) ($series[$day->toDateString()] ?? 0), 'tooltip' => $day->translatedFormat('l, d M')];
        })->all();

        return ['visits' => $points($visits), 'revenue' => $points($revenue)];
    }
}
