@extends('layouts.app', ['title' => $title])

@section('content')
  <x-page-head :title="$title" :subtitle="$from->translatedFormat('d M Y').' – '.$to->translatedFormat('d M Y').' · transaksi lunas (void tidak dihitung)'" :crumbs="['Analitik', 'Laporan' => route('reports.index')]" />

  @include('reports.partials.filter')

  @php
    $rp = fn ($v) => 'Rp'.number_format($v, 0, ',', '.');
    $net = $totals['collected'] - $totals['refund'];
  @endphp
  <div class="row g-4 mb-4">
    @foreach ([
      ['Transaksi', number_format($totals['count'], 0, ',', '.'), $totals['voided'].' void di periode ini'],
      ['Diterima dari pasien (bersih)', $rp($net), 'setelah retur '.$rp($totals['refund'])],
      ['Nilai layanan', $rp($totals['value']), 'termasuk BPJS/gratis'],
      ['HPP obat', $rp(round($totals['cost'])), 'margin kotor '.$rp($totals['value'] - round($totals['cost']))],
    ] as [$label, $value, $foot])
      <div class="col-sm-6 col-xl-3">
        <div class="card nx-kpi h-100"><div class="card-body">
          <span class="nx-kpi-label">{{ $label }}</span>
          <div class="nx-kpi-value fs-4 nx-num">{{ $value }}</div>
          <div class="nx-kpi-foot"><span>{{ $foot }}</span></div>
        </div></div>
      </div>
    @endforeach
  </div>

  <div class="row g-4">
    <div class="col-xl-8">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Diterima dari pasien per hari</h5></div>
        <div class="card-body pt-4">
          <x-bar-chart format="rupiah" label="Uang diterima dari pasien per hari" :points="$days->map(fn ($d) => ['label' => $days->count() > 14 ? $d['date']->format('j') : $d['date']->format('d/m'), 'value' => $d['collected'], 'tooltip' => $d['date']->translatedFormat('l, d M Y').' · '.$d['count'].' transaksi'])->values()->all()" />
        </div>
      </div>
      <div class="card">
        <div class="table-responsive">
          <table class="table table-sm mb-0 nx-table-compact">
            <thead><tr><th>Tanggal</th><th class="text-end">Transaksi</th><th class="text-end">OTC</th><th class="text-end">Nilai layanan</th><th class="text-end">Diterima</th><th class="text-end">Retur</th><th class="text-end">HPP</th></tr></thead>
            <tbody>
              @foreach ($days->filter(fn ($d) => $d['count'] || $d['refund']) as $d)
                <tr>
                  <td class="nx-num">{{ $d['date']->translatedFormat('D, d/m') }}</td>
                  <td class="text-end nx-num">{{ $d['count'] }}</td>
                  <td class="text-end nx-num">{{ $d['otc'] }}</td>
                  <td class="text-end nx-num">{{ $rp($d['value']) }}</td>
                  <td class="text-end nx-num">{{ $rp($d['collected']) }}</td>
                  <td class="text-end nx-num">{{ $d['refund'] ? '−'.$rp($d['refund']) : '—' }}</td>
                  <td class="text-end nx-num text-muted">{{ $rp(round($d['cost'])) }}</td>
                </tr>
              @endforeach
              @if ($days->every(fn ($d) => ! $d['count'] && ! $d['refund']))
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada transaksi di periode ini.</td></tr>
              @endif
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="card">
        <div class="card-header"><h5 class="mb-0">Per jenis bayar</h5></div>
        <ul class="list-group list-group-flush">
          @forelse ($payment_types as $pt)
            <li class="list-group-item">
              <div class="d-flex justify-content-between"><span class="fw-semibold">{{ $pt['name'] }}</span><span class="nx-num">{{ $rp($pt['value']) }}</span></div>
              <div class="small text-muted d-flex justify-content-between"><span>{{ $pt['count'] }} transaksi</span><span>diterima {{ $rp($pt['collected']) }}</span></div>
            </li>
          @empty
            <li class="list-group-item small text-muted">Belum ada transaksi.</li>
          @endforelse
        </ul>
      </div>
    </div>
  </div>
@endsection
