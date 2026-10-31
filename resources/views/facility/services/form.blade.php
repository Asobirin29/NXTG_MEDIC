@extends('layouts.app', ['title' => $service->exists ? 'Ubah Tindakan' : 'Tambah Tindakan'])

@section('content')
  <x-page-head :title="$service->exists ? 'Ubah Tindakan' : 'Tambah Tindakan'"
               :crumbs="['Pengaturan', 'Tindakan Medis' => route('settings.services.index')]" />

  <form method="POST" action="{{ $service->exists ? route('settings.services.update', $service) : route('settings.services.store') }}" novalidate>
    @csrf
    @if ($service->exists) @method('PUT') @endif

    <div class="row g-4">
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header"><h5 class="mb-0">Tindakan</h5></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-sm-5">
                <label class="form-label" for="code">Kode</label>
                <input class="form-control text-uppercase @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code', $service->code) }}" required>
                @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-sm-7">
                <label class="form-label" for="poli_id">Poli</label>
                <select class="form-select @error('poli_id') is-invalid @enderror" id="poli_id" name="poli_id">
                  <option value="">Semua poli</option>
                  @foreach ($polis as $poli)
                    <option value="{{ $poli->id }}" @selected(old('poli_id', $service->poli_id) == $poli->id)>{{ $poli->name }}</option>
                  @endforeach
                </select>
                @error('poli_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-12">
                <label class="form-label" for="name">Nama tindakan</label>
                <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $service->name) }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-12"><x-active-switch :checked="$service->is_active" /></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-header"><h5 class="mb-0">Tarif per jenis pembayaran</h5></div>
          <div class="card-body">
            @foreach ($paymentTypes as $type)
              <div class="row g-2 align-items-center mb-2">
                <label class="col-sm-5 col-form-label" for="price_{{ $type->id }}">
                  {{ $type->name }}
                  @unless ($type->patient_pays)<span class="badge badge-soft-info ms-1">Rp0 ke pasien</span>@endunless
                </label>
                <div class="col-sm-7">
                  <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input class="form-control nx-num text-end @error('prices.'.$type->id) is-invalid @enderror" type="number" min="0" step="1"
                           id="price_{{ $type->id }}" name="prices[{{ $type->id }}]" value="{{ old('prices.'.$type->id, $service->exists ? $service->priceFor($type) : null) }}">
                  </div>
                </div>
              </div>
            @endforeach
            <div class="form-text">Kosongkan bila tindakan tidak tersedia untuk jenis pembayaran tersebut.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2 mt-4">
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>Simpan</button>
      <a class="btn btn-soft-secondary" href="{{ route('settings.services.index') }}">Batal</a>
    </div>
  </form>
@endsection
