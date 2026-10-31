@extends('layouts.platform', ['title' => $tenant->exists ? 'Ubah Faskes' : 'Faskes Baru'])

@section('content')
  <div class="nx-page-head">
    <nav class="nx-breadcrumb" aria-label="Breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('platform.tenants.index') }}">Faskes</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $tenant->exists ? 'Ubah' : 'Baru' }}</li>
      </ol>
    </nav>
    <h1>{{ $tenant->exists ? $tenant->name : 'Faskes Baru' }}</h1>
  </div>

  <form method="POST" action="{{ $tenant->exists ? route('platform.tenants.update', $tenant) : route('platform.tenants.store') }}" novalidate
        @if ($tenant->exists) data-confirm="Simpan perubahan faskes ini?" @endif>
    @csrf
    @if ($tenant->exists) @method('PUT') @endif

    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card">
          <div class="card-header"><h5 class="mb-0">Data faskes</h5></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-8">
                <label class="form-label" for="name">Nama faskes</label>
                <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $tenant->name) }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-4">
                <label class="form-label" for="code">Kode</label>
                <input class="form-control text-uppercase @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code', $tenant->code) }}" required>
                @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label" for="type">Jenis</label>
                <select class="form-select" id="type" name="type">
                  @foreach (['klinik' => 'Klinik', 'puskesmas' => 'Puskesmas'] as $v => $l)
                    <option value="{{ $v }}" @selected(old('type', $tenant->type) === $v)>{{ $l }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="plan">Paket langganan</label>
                <select class="form-select" id="plan" name="plan">
                  @foreach ($plans as $v => $l)
                    <option value="{{ $v }}" @selected(old('plan', $tenant->plan) === $v)>{{ $l }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-8">
                <label class="form-label" for="address">Alamat</label>
                <input class="form-control" id="address" name="address" value="{{ old('address', $tenant->address) }}">
              </div>
              <div class="col-md-4">
                <label class="form-label" for="phone">Telepon</label>
                <input class="form-control" id="phone" name="phone" value="{{ old('phone', $tenant->phone) }}">
              </div>
              @if ($tenant->exists)
                <div class="col-md-6">
                  <label class="form-label" for="status">Status</label>
                  <select class="form-select" id="status" name="status">
                    <option value="active" @selected(old('status', $tenant->status) === 'active')>Aktif</option>
                    <option value="suspended" @selected(old('status', $tenant->status) === 'suspended')>Ditangguhkan</option>
                  </select>
                  <div class="form-text">Faskes yang ditangguhkan tidak bisa login.</div>
                </div>
              @endif
            </div>
          </div>
        </div>
      </div>

      @unless ($tenant->exists)
        <div class="col-lg-5">
          <div class="card">
            <div class="card-header"><h5 class="mb-0">Admin pertama</h5></div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label" for="admin_name">Nama</label>
                <input class="form-control @error('admin_name') is-invalid @enderror" id="admin_name" name="admin_name" value="{{ old('admin_name') }}" required>
                @error('admin_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="mb-3">
                <label class="form-label" for="admin_email">Email</label>
                <input class="form-control @error('admin_email') is-invalid @enderror" type="email" id="admin_email" name="admin_email" value="{{ old('admin_email') }}" required>
                @error('admin_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="mb-0">
                <label class="form-label" for="admin_password">Password awal</label>
                <input class="form-control @error('admin_password') is-invalid @enderror" type="password" id="admin_password" name="admin_password" autocomplete="new-password" required>
                @error('admin_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>
          </div>
        </div>
      @endunless
    </div>

    <div class="d-flex gap-2 mt-4">
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>Simpan</button>
      <a class="btn btn-soft-secondary" href="{{ route('platform.tenants.index') }}">Batal</a>
    </div>
  </form>
@endsection
