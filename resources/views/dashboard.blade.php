@extends('layouts.app', ['title' => 'Beranda'])

@section('content')
  <x-page-head :title="'Selamat datang, '.$user->name" :subtitle="$tenant->name.' · '.$user->role->label()" />

  {{-- Kartu hari ini: isi menyesuaikan peran (yang perlu ditindaklanjuti lebih dulu). --}}
  @php
    $cards = [['Kunjungan hari ini', $visits['total'], 'bi-people', 'primary', $visits['done'].' selesai', route('registration.index'), 'registration.access']];
    if ($user->can('emr.access')) {
        $cards[] = ['Menunggu perawat', $visits['waiting_nurse'], 'bi-heart-pulse', 'info', 'belum asesmen', route('emr.index'), 'emr.vitals'];
        $cards[] = ['Menunggu dokter', $visits['waiting_doctor'], 'bi-clipboard2-pulse', 'warning', $visits['in_consultation'].' sedang diperiksa', route('emr.index'), 'emr.access'];
    }
    if ($posQueue) {
        $cards[] = ['Resep menunggu verifikasi', $posQueue['rx'], 'bi-capsule', 'danger', 'dari ruang periksa', route('pos.index'), 'pos.access'];
        $cards[] = ['Pasien siap bayar', $posQueue['billing'], 'bi-cash-coin', 'warning', 'diterima hari ini Rp'.number_format($posQueue['collected'], 0, ',', '.'), route('pos.index'), 'pos.access'];
    }
  @endphp
  <div class="row g-4 mb-4">
    @foreach ($cards as [$label, $value, $icon, $tone, $foot, $url, $ability])
      <div class="col-sm-6 col-xl">
        @can($ability)<a href="{{ $url }}" class="card nx-kpi nx-card-lift h-100 text-decoration-none">@else<div class="card nx-kpi h-100">@endcan
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start"><span class="nx-kpi-label">{{ $label }}</span><span class="nx-icon-sq {{ $tone }}"><i class="bi {{ $icon }}"></i></span></div>
            <div class="nx-kpi-value nx-num">{{ number_format($value, 0, ',', '.') }}</div>
            <div class="nx-kpi-foot"><span>{{ $foot }}</span></div>
          </div>
        @can($ability)</a>@else</div>@endcan
      </div>
    @endforeach
  </div>

  @if ($trend)
    <div class="row g-4 mb-4">
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header d-flex align-items-center"><h5 class="mb-0 me-auto">Kunjungan · 14 hari</h5><span class="small text-muted">tanpa yang batal</span></div>
          <div class="card-body pt-4"><x-bar-chart :points="$trend['visits']" label="Jumlah kunjungan per hari, 14 hari terakhir" /></div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header d-flex align-items-center"><h5 class="mb-0 me-auto">Diterima dari pasien · 14 hari</h5>@can('reports.view')<a class="small" href="{{ route('reports.show', 'penjualan') }}">Laporan</a>@endcan</div>
          <div class="card-body pt-4"><x-bar-chart :points="$trend['revenue']" format="rupiah" label="Uang diterima dari pasien per hari, 14 hari terakhir" /></div>
        </div>
      </div>
    </div>
  @endif

  @if ($stockAlerts)
    <div class="row g-4 mb-4">
      @include('inventory.stock.partials.kpis', ['counts' => $stockAlerts['counts']])
    </div>
    <div class="row g-4 mb-4">
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header d-flex align-items-center"><h5 class="mb-0 me-auto">Stok menipis</h5><a class="small" href="{{ route('stock.index', ['filter' => 'low']) }}">Semua</a></div>
          <ul class="list-group list-group-flush">
            @forelse ($stockAlerts['low'] as $drug)
              <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                <a href="{{ route('stock.show', $drug) }}" class="text-reset fw-semibold">{{ $drug->name }}</a>
                <span class="small text-end">
                  @if ($drug->available_stock === 0)<span class="badge badge-soft-danger"><i class="bi bi-x-circle"></i> Habis</span>
                  @else<x-stock-qty :qty="$drug->available_stock" :drug="$drug" />@endif
                  <span class="text-muted d-block">min. {{ number_format($drug->min_stock, 0, ',', '.') }} {{ $drug->base_unit }}</span>
                </span>
              </li>
            @empty
              <li class="list-group-item small text-muted">Semua obat di atas ambang minimum.</li>
            @endforelse
          </ul>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header d-flex align-items-center"><h5 class="mb-0 me-auto">Kedaluwarsa & ED ≤ 90 hari</h5><a class="small" href="{{ route('stock.index', ['filter' => 'expiring']) }}">Semua</a></div>
          <ul class="list-group list-group-flush">
            @forelse ($stockAlerts['expiring'] as $batch)
              <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                <span><a href="{{ route('stock.show', $batch->drug) }}" class="text-reset fw-semibold">{{ $batch->drug->name }}</a> <span class="small text-muted nx-num">· {{ $batch->batch_number }}</span></span>
                <span class="small text-end"><x-expiry :date="$batch->expiry_date" /><span class="d-block text-muted"><x-stock-qty :qty="$batch->qty_on_hand" :drug="$batch->drug" /></span></span>
              </li>
            @empty
              <li class="list-group-item small text-muted">Tidak ada batch yang mendekati kedaluwarsa.</li>
            @endforelse
          </ul>
        </div>
      </div>
    </div>
  @endif

  <div class="row g-4">
    @foreach ($shortcuts as $section)
      @continue($section['label'] === 'Utama')
      @foreach ($section['items'] as $item)
        @foreach ($item['children'] ?: [$item] as $page)
          <div class="col-sm-6 col-xl-3">
            <a href="{{ $page['url'] }}" class="card h-100 text-decoration-none">
              <div class="card-body d-flex gap-3 align-items-start">
                <span class="nx-icon-sq primary"><i class="bi {{ $page['icon'] ?? $item['icon'] }}"></i></span>
                <span>
                  <span class="d-block fw-semibold" style="color:var(--nx-text)">{{ $page['label'] }}</span>
                  <span class="small text-muted">{{ $section['label'] }}</span>
                </span>
              </div>
            </a>
          </div>
        @endforeach
      @endforeach
    @endforeach
  </div>
@endsection
