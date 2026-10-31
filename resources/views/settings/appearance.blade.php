@extends('layouts.app', ['title' => 'Tampilan'])

@php
  $swatches = [
    'teal' => ['#0f766e', '#e3f4f1', '#f2f6f7', '#134e4a'],
    'indigo' => ['#6366f1', '#eef2ff', '#f2f4fc', '#312e81'],
  ];
  $descriptions = [
    'teal' => 'Tenang dan klinis: latar netral sejuk dengan aksen hijau kebiruan. Disarankan untuk faskes.',
    'indigo' => 'Warna bawaan template NexaDash dengan aksen indigo.',
  ];
@endphp

@section('content')
  <x-page-head title="Tampilan" subtitle="Tema warna berlaku untuk seluruh pengguna di faskes ini. Mode gelap/terang diatur masing-masing pengguna lewat tombol di header." :crumbs="['Pengaturan']" />

  <form method="POST" action="{{ route('settings.appearance.update') }}">
    @csrf
    @method('PUT')
    <div class="row g-4">
      @foreach ($themes as $key => $label)
        <div class="col-md-6">
          <label class="nx-theme-option">
            <input type="radio" name="theme" value="{{ $key }}" @checked($current === $key)>
            <div class="card h-100">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                  <div>
                    <div class="fw-semibold" style="color:var(--nx-text)">{{ $label }}</div>
                    <div class="small text-muted">{{ $descriptions[$key] }}</div>
                  </div>
                  <div class="nx-theme-swatches ms-3">
                    @foreach ($swatches[$key] as $color)
                      <span style="background:{{ $color }}"></span>
                    @endforeach
                  </div>
                </div>
                <div class="nx-theme-preview" style="background:{{ $swatches[$key][2] }}">
                  <div class="pv-side" style="background:#fff">
                    <i class="on" style="background:{{ $swatches[$key][0] }}"></i><i></i><i></i><i></i>
                  </div>
                  <div class="pv-main">
                    <div class="pv-card"></div>
                    <div class="pv-btn" style="background:{{ $swatches[$key][0] }}"></div>
                  </div>
                </div>
              </div>
            </div>
          </label>
        </div>
      @endforeach
    </div>
    @error('theme')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
    <div class="mt-4">
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>Simpan tema</button>
    </div>
  </form>
@endsection
