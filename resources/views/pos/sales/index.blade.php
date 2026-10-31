@extends('layouts.app', ['title' => 'Riwayat Transaksi'])

@section('content')
  <x-page-head title="Riwayat Transaksi" subtitle="Transaksi lunas dan void. Buka transaksi untuk cetak ulang struk, void, atau retur." :crumbs="['Farmasi & Kasir' => route('pos.index')]">
    <a href="{{ route('pos.index') }}" class="btn btn-primary"><i class="bi bi-cart3 me-1"></i>Kembali ke kasir</a>
  </x-page-head>

  <div class="row g-4 mb-4">
    @foreach ([['Transaksi hari ini', number_format($todayCount, 0, ',', '.'), 'bi-receipt', 'primary'], ['Diterima dari pasien', 'Rp'.number_format($todayCollected, 0, ',', '.'), 'bi-cash-coin', 'success'], ['Nilai layanan (termasuk BPJS/gratis)', 'Rp'.number_format($todayValue, 0, ',', '.'), 'bi-clipboard-data', 'info']] as [$label, $value, $icon, $tone])
      <div class="col-md-4">
        <div class="card nx-kpi h-100"><div class="card-body">
          <div class="d-flex justify-content-between align-items-start"><span class="nx-kpi-label">{{ $label }}</span><span class="nx-icon-sq {{ $tone }}"><i class="bi {{ $icon }}"></i></span></div>
          <div class="nx-kpi-value fs-4 nx-num">{{ $value }}</div>
        </div></div>
      </div>
    @endforeach
  </div>

  <div class="card">
    <div class="card-header">
      <form class="row g-2 w-100" method="GET">
        <div class="col-md-5 col-lg-4"><input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="No. transaksi, pasien, atau penanda" aria-label="Cari transaksi"></div>
        <div class="col-sm-4 col-md-3"><input class="form-control nx-num" type="date" name="date" value="{{ request('date') }}" aria-label="Tanggal"></div>
        <div class="col-auto"><button class="btn btn-soft-secondary" type="submit" data-allow-resubmit>Terapkan</button></div>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0 nx-table-compact">
        <thead><tr><th>No.</th><th>Waktu</th><th>Pembeli / pasien</th><th>Jenis bayar</th><th class="text-end">Tagihan</th><th>Status</th><th>Kasir</th><th></th></tr></thead>
        <tbody>
          @forelse ($sales as $s)
            <tr>
              <td class="nx-num fw-semibold">{{ $s->number }}</td>
              <td class="nx-num small">{{ $s->completed_at?->format('d/m/Y H:i') }}</td>
              <td>{{ $s->title() }} <span class="small text-muted">· {{ $s->isEncounter() ? 'Kunjungan' : 'OTC' }}</span></td>
              <td class="small">{{ $s->paymentType->name }}</td>
              <td class="text-end"><x-rupiah :value="$s->amount_due" /></td>
              <td><span class="badge {{ $s->statusBadge() }}">{{ $s->statusLabel() }}</span></td>
              <td class="small">{{ $s->cashier?->name }}</td>
              <td class="text-end"><a class="btn btn-sm btn-soft-secondary" href="{{ route('pos.sales.show', $s) }}">Lihat</a></td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada transaksi.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($sales->hasPages())<div class="card-footer">{{ $sales->links() }}</div>@endif
  </div>
@endsection
