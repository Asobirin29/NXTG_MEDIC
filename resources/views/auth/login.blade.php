@extends('layouts.auth', ['title' => 'Masuk'])

@section('brand')
  <h2>Dari loket sampai apotek, satu alur digital.</h2>
  <div class="nx-auth-feature"><i class="bi bi-clipboard2-pulse"></i><div><div class="nx-af-title">Rekam medis elektronik</div><div class="nx-af-text">Tanda vital, SOAP, diagnosis ICD-10, dan e-Resep dalam satu layar.</div></div></div>
  <div class="nx-auth-feature"><i class="bi bi-shield-check"></i><div><div class="nx-af-title">Pasien aman sejak ruang periksa</div><div class="nx-af-text">Alergi dan interaksi obat terdeteksi otomatis saat resep dibuat.</div></div></div>
  <div class="nx-auth-feature"><i class="bi bi-box-seam"></i><div><div class="nx-af-title">Stok akurat per batch</div><div class="nx-af-text">FEFO otomatis, peringatan stok menipis dan obat mendekati kedaluwarsa.</div></div></div>
@endsection

@section('content')
  <h1 class="h2 mb-1">Masuk</h1>
  <p class="text-muted mb-4">Gunakan akun yang diberikan admin faskes Anda.</p>

  <form method="POST" action="{{ route('login.attempt') }}" novalidate>
    @csrf
    <div class="mb-3">
      <label class="form-label" for="email">Email</label>
      <input class="form-control @error('email') is-invalid @enderror" type="email" id="email" name="email"
             value="{{ old('email') }}" autocomplete="username" required autofocus>
      @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
      <label class="form-label" for="password">Password</label>
      <div class="input-group has-validation">
        <input class="form-control @error('password') is-invalid @enderror" type="password" id="password" name="password"
               autocomplete="current-password" required>
        <button class="btn btn-outline-secondary btn-toggle-pw" type="button" data-target="#password" aria-label="Tampilkan password"><i class="bi bi-eye"></i></button>
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
    </div>
    <div class="form-check form-switch mb-4">
      <input class="form-check-input" type="checkbox" role="switch" id="remember" name="remember" value="1">
      <label class="form-check-label" for="remember">Ingat saya di perangkat ini</label>
    </div>
    <button class="btn btn-primary w-100 btn-lg" type="submit">Masuk</button>
  </form>
@endsection
