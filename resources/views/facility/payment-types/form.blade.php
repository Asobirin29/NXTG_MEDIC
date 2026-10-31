@extends('layouts.app', ['title' => $type->exists ? 'Ubah Jenis Pembayaran' : 'Tambah Jenis Pembayaran'])

@section('content')
  <x-page-head :title="$type->exists ? 'Ubah Jenis Pembayaran' : 'Tambah Jenis Pembayaran'"
               :crumbs="['Pengaturan', 'Jenis Pembayaran' => route('settings.payment-types.index')]" />

  <div class="row"><div class="col-lg-6">
    <form method="POST" action="{{ $type->exists ? route('settings.payment-types.update', $type) : route('settings.payment-types.store') }}" class="card" novalidate>
      @csrf
      @if ($type->exists) @method('PUT') @endif
      <div class="card-body">
        <div class="row g-3">
          <div class="col-sm-4">
            <label class="form-label" for="code">Kode</label>
            <input class="form-control text-uppercase @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code', $type->code) }}" required>
            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-sm-5">
            <label class="form-label" for="name">Nama</label>
            <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $type->name) }}" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-sm-3">
            <label class="form-label" for="sort_order">Urutan</label>
            <input class="form-control nx-num @error('sort_order') is-invalid @enderror" type="number" min="0" id="sort_order" name="sort_order" value="{{ old('sort_order', $type->sort_order) }}" required>
            @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-12">
            <x-active-switch name="patient_pays" :checked="$type->patient_pays" label="Pasien membayar tagihan" />
            <div class="form-text ms-5">Matikan untuk BPJS/Program/Gratis: tagihan ke pasien Rp0, tetapi nilai obat dan tindakan tetap tercatat untuk laporan.</div>
          </div>
          <div class="col-12"><x-active-switch :checked="$type->is_active" label="Aktif" /></div>
        </div>
      </div>
      <div class="card-footer d-flex gap-2">
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>Simpan</button>
        <a class="btn btn-soft-secondary" href="{{ route('settings.payment-types.index') }}">Batal</a>
      </div>
    </form>
  </div></div>
@endsection
