@extends('layouts.app', ['title' => $poli->exists ? 'Ubah Poli' : 'Tambah Poli'])

@section('content')
  <x-page-head :title="$poli->exists ? 'Ubah Poli' : 'Tambah Poli'" :crumbs="['Pengaturan', 'Poli' => route('settings.polis.index')]" />

  <div class="row"><div class="col-lg-6">
    <form method="POST" action="{{ $poli->exists ? route('settings.polis.update', $poli) : route('settings.polis.store') }}" class="card" novalidate>
      @csrf
      @if ($poli->exists) @method('PUT') @endif
      <div class="card-body">
        <div class="row g-3">
          <div class="col-sm-4">
            <label class="form-label" for="code">Kode</label>
            <input class="form-control text-uppercase @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code', $poli->code) }}" required>
            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-sm-8">
            <label class="form-label" for="name">Nama poli</label>
            <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $poli->name) }}" placeholder="mis. Poli Umum, Poli Gigi, KIA" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-12"><x-active-switch :checked="$poli->is_active" label="Poli aktif (bisa dipilih saat pendaftaran)" /></div>
        </div>
      </div>
      <div class="card-footer d-flex gap-2">
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>Simpan</button>
        <a class="btn btn-soft-secondary" href="{{ route('settings.polis.index') }}">Batal</a>
      </div>
    </form>
  </div></div>
@endsection
