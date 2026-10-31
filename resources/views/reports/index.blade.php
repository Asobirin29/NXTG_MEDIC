@extends('layouts.app', ['title' => 'Laporan'])

@section('content')
  <x-page-head title="Laporan" subtitle="Pilih laporan; semua bisa difilter per periode dan diunduh sebagai Excel." :crumbs="['Analitik']" />

  <div class="row g-4">
    @foreach ($reports as $key => [$label, $icon, $desc])
      <div class="col-md-6 col-xl-4">
        <a href="{{ route('reports.show', $key) }}" class="card nx-card-lift h-100 text-decoration-none">
          <div class="card-body d-flex gap-3">
            <span class="nx-icon-sq primary"><i class="bi {{ $icon }}"></i></span>
            <span>
              <span class="d-block fw-semibold" style="color:var(--nx-text)">{{ $label }}</span>
              <span class="small text-muted">{{ $desc }}</span>
            </span>
          </div>
        </a>
      </div>
    @endforeach
  </div>
@endsection
