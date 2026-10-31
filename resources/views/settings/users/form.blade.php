@extends('layouts.app', ['title' => $user->exists ? 'Ubah Pengguna' : 'Tambah Pengguna'])

@section('content')
  <x-page-head :title="$user->exists ? 'Ubah Pengguna' : 'Tambah Pengguna'"
               :crumbs="['Pengaturan', 'Pengguna' => route('settings.users.index')]" />

  <form method="POST" action="{{ $user->exists ? route('settings.users.update', $user) : route('settings.users.store') }}" novalidate>
    @csrf
    @if ($user->exists) @method('PUT') @endif

    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card">
          <div class="card-header"><h5 class="mb-0">Identitas & role</h5></div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label" for="name">Nama lengkap (dengan gelar)</label>
              <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required>
              @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
              <label class="form-label" for="email">Email</label>
              <input class="form-control @error('email') is-invalid @enderror" type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required>
              @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" for="role">Role</label>
                <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
                  @foreach ($roles as $value => $label)
                    <option value="{{ $value }}" @selected(old('role', $user->role?->value) === $value)>{{ $label }}</option>
                  @endforeach
                </select>
                @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label" for="license_number">Nomor SIP/STR</label>
                <input class="form-control @error('license_number') is-invalid @enderror" id="license_number" name="license_number" value="{{ old('license_number', $user->license_number) }}">
                <div class="form-text">Wajib untuk dokter, perawat/bidan, dan apoteker.</div>
                @error('license_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>
            <div class="form-check form-switch mt-3">
              <input type="hidden" name="is_active" value="0">
              <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
              <label class="form-check-label" for="is_active">Akun aktif</label>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card">
          <div class="card-header"><h5 class="mb-0">Keamanan</h5></div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label" for="password">Password</label>
              <input class="form-control @error('password') is-invalid @enderror" type="password" id="password" name="password" autocomplete="new-password">
              <div class="form-text">{{ $user->exists ? 'Kosongkan bila tidak diganti.' : 'Minimal 8 karakter.' }}</div>
              @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
              <label class="form-label" for="password_confirmation">Ulangi password</label>
              <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password">
            </div>
            <div class="mb-0">
              <label class="form-label" for="pin">PIN (4–6 angka)</label>
              <input class="form-control nx-num @error('pin') is-invalid @enderror" type="password" id="pin" name="pin" inputmode="numeric" maxlength="6" autocomplete="off">
              <div class="form-text">Untuk ganti pengguna cepat dan persetujuan peringatan oleh apoteker. {{ $user->exists && $user->hasPin() ? 'Kosongkan bila tidak diganti.' : '' }}</div>
              @error('pin')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2 mt-4">
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>Simpan</button>
      <a class="btn btn-soft-secondary" href="{{ route('settings.users.index') }}">Batal</a>
    </div>
  </form>
@endsection
