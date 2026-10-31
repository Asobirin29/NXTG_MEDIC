@extends('layouts.app', ['title' => $patient->exists ? 'Ubah Data Pasien' : 'Pasien Baru'])

@section('content')
  <x-page-head :title="$patient->exists ? 'Ubah Data Pasien' : 'Pasien Baru'"
               :subtitle="$patient->exists ? 'No. RM '.$patient->medical_record_number : 'No. RM dibuat otomatis setelah disimpan.'"
               :crumbs="['Pasien' => route('patients.index')]" />

  <form method="POST" action="{{ $patient->exists ? route('patients.update', $patient) : route('patients.store') }}" novalidate>
    @csrf
    @if ($patient->exists) @method('PUT') @endif
    @if ($next)<input type="hidden" name="next" value="{{ $next }}">@endif

    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card h-100">
          <div class="card-header"><h5 class="mb-0">Identitas</h5></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label" for="name">Nama lengkap (sesuai KTP)</label>
                <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $patient->name) }}" required autofocus>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label" for="nik">NIK</label>
                <input class="form-control nx-num @error('nik') is-invalid @enderror" id="nik" name="nik" value="{{ old('nik', $patient->nik) }}" inputmode="numeric" maxlength="20" autocomplete="off">
                <div class="form-text"><i class="bi bi-lock"></i> Disimpan terenkripsi.</div>
                @error('nik')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label" for="birth_date">Tanggal lahir</label>
                <input class="form-control @error('birth_date') is-invalid @enderror" type="date" id="birth_date" name="birth_date" max="{{ today()->toDateString() }}"
                       value="{{ old('birth_date', $patient->birth_date?->toDateString()) }}" required>
                @error('birth_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <span class="form-label d-block">Jenis kelamin</span>
                <div class="d-flex gap-3 pt-1">
                  @foreach (['L' => 'Laki-laki', 'P' => 'Perempuan'] as $value => $label)
                    <div class="form-check">
                      <input class="form-check-input @error('gender') is-invalid @enderror" type="radio" name="gender" id="gender_{{ $value }}" value="{{ $value }}" @checked(old('gender', $patient->gender) === $value)>
                      <label class="form-check-label" for="gender_{{ $value }}">{{ $label }}</label>
                    </div>
                  @endforeach
                </div>
                @error('gender')<div class="text-danger small">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-6">
                <label class="form-label" for="blood_type">Golongan darah</label>
                <select class="form-select" id="blood_type" name="blood_type">
                  <option value="">Tidak diketahui</option>
                  @foreach (['A', 'B', 'AB', 'O'] as $bt)
                    <option value="{{ $bt }}" @selected(old('blood_type', $patient->blood_type) === $bt)>{{ $bt }}</option>
                  @endforeach
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="card h-100">
          <div class="card-header"><h5 class="mb-0">Kontak & jaminan</h5></div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label" for="phone">No. HP</label>
              <input class="form-control nx-num" id="phone" name="phone" value="{{ old('phone', $patient->phone) }}" inputmode="tel">
            </div>
            <div class="mb-3">
              <label class="form-label" for="address">Alamat</label>
              <textarea class="form-control" id="address" name="address" rows="2">{{ old('address', $patient->address) }}</textarea>
            </div>
            <div class="mb-3">
              <label class="form-label" for="insurance_number">No. peserta BPJS / asuransi</label>
              <input class="form-control nx-num" id="insurance_number" name="insurance_number" value="{{ old('insurance_number', $patient->insurance_number) }}">
            </div>
            @if ($patient->exists)
              <x-active-switch :checked="$patient->is_active" label="Pasien aktif" />
            @endif
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2 mt-4">
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>{{ $next === 'register' ? 'Simpan & lanjut daftarkan' : 'Simpan' }}</button>
      <a class="btn btn-soft-secondary" href="{{ $patient->exists ? route('patients.show', $patient) : route('patients.index') }}">Batal</a>
    </div>
  </form>
@endsection
