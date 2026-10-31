{{-- Kartu peringatan stok (PRD §6.4): dipakai di halaman Stok & Batch dan Beranda apoteker. --}}
@php
  $cards = [
    ['low', 'Stok menipis', 'bi-graph-down-arrow', 'warning', 'obat ≤ ambang minimum'],
    ['expiring', 'ED ≤ 90 hari', 'bi-hourglass-split', 'info', 'batch, keluarkan lebih dulu'],
    ['expired', 'Kedaluwarsa', 'bi-x-octagon', 'danger', 'batch terblokir, masih bersaldo'],
  ];
@endphp
@foreach ($cards as [$key, $label, $icon, $tone, $foot])
  <div class="col-sm-6 col-xl-3">
    <a href="{{ route('stock.index', ['filter' => $key]) }}" class="card nx-kpi nx-card-lift h-100 text-decoration-none @if (($filter ?? null) === $key) border-{{ $tone }} @endif">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start"><span class="nx-kpi-label">{{ $label }}</span><span class="nx-icon-sq {{ $tone }}"><i class="bi {{ $icon }}"></i></span></div>
        <div class="nx-kpi-value">{{ number_format($counts[$key], 0, ',', '.') }}</div>
        <div class="nx-kpi-foot"><span>{{ $foot }}</span></div>
      </div>
    </a>
  </div>
@endforeach
