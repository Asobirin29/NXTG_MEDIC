@extends('layouts.app', ['title' => 'Ganti Pengguna'])

@section('content')
  <x-page-head title="Ganti Pengguna" subtitle="Pindah akun cepat di komputer bersama dengan PIN pribadi." />

  <div class="row justify-content-center">
    <div class="col-lg-6">
      <div class="card">
        <div class="card-body">
          @if ($users->isEmpty())
            <div class="text-center text-muted py-4">
              <i class="bi bi-person-lock d-block fs-3 mb-2"></i>
              Belum ada pengguna lain dengan PIN di faskes ini. Admin dapat mengatur PIN di menu Pengaturan &rarr; Pengguna.
            </div>
          @else
            <form method="POST" action="{{ route('switch-user.attempt') }}">
              @csrf
              <div class="mb-3">
                <label class="form-label" for="user_id">Pengguna</label>
                <select class="form-select @error('user_id') is-invalid @enderror" id="user_id" name="user_id" required>
                  @foreach ($users as $u)
                    <option value="{{ $u->id }}" @selected(old('user_id') == $u->id)>{{ $u->name }} &mdash; {{ $u->role->label() }}</option>
                  @endforeach
                </select>
                @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="mb-4">
                <label class="form-label" for="pin">PIN</label>
                <input class="form-control form-control-lg nx-num @error('pin') is-invalid @enderror" type="password" id="pin" name="pin"
                       inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="off" required>
                @error('pin')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <button class="btn btn-primary w-100" type="submit"><i class="bi bi-arrow-left-right me-1"></i>Ganti pengguna</button>
            </form>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection
