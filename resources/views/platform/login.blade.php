@extends('layouts.auth', ['title' => 'Admin Platform', 'brandTheme' => 'indigo', 'brandName' => 'Admin Platform'])

@section('brand')
  <h2>Kelola faskes pelanggan.</h2>
  <div class="nx-auth-feature"><i class="bi bi-hospital"></i><div><div class="nx-af-title">Faskes baru tanpa menyentuh server</div><div class="nx-af-text">Buat tenant, admin pertama, dan paket langganan.</div></div></div>
  <div class="nx-auth-feature"><i class="bi bi-pause-circle"></i><div><div class="nx-af-title">Penangguhan akun</div><div class="nx-af-text">Tangguhkan atau aktifkan kembali akses faskes.</div></div></div>
@endsection

@section('content')
  <h1 class="h2 mb-1">Masuk Admin Platform</h1>
  <p class="text-muted mb-4">Khusus pengelola layanan.</p>

  <form method="POST" action="{{ route('platform.login.attempt') }}" novalidate>
    @csrf
    <div class="mb-3">
      <label class="form-label" for="email">Email</label>
      <input class="form-control @error('email') is-invalid @enderror" type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
      @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-4">
      <label class="form-label" for="password">Password</label>
      <input class="form-control" type="password" id="password" name="password" autocomplete="current-password" required>
    </div>
    <button class="btn btn-primary w-100 btn-lg" type="submit">Masuk</button>
  </form>
@endsection
