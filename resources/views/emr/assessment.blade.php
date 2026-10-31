@extends('layouts.app', ['title' => 'Asesmen Awal'])

@php
  $fields = [
      ['systolic', 'Sistolik', 'mmHg', '1'], ['diastolic', 'Diastolik', 'mmHg', '1'],
      ['heart_rate', 'Nadi', 'x/mnt', '1'], ['respiratory_rate', 'Laju napas', 'x/mnt', '1'],
      ['temperature', 'Suhu', '°C', '0.1'], ['spo2', 'SpO₂', '%', '1'],
      ['weight', 'Berat badan', 'kg', '0.01'], ['height', 'Tinggi badan', 'cm', '0.1'],
  ];
  $last = $encounter->vitalSigns->first();
@endphp

@section('content')
  <x-page-head title="Asesmen Awal" :subtitle="$encounter->queueLabel().' · '.$encounter->poli->name" :crumbs="['Antrean Poli' => route('emr.index')]" />

  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-body">
          <div class="fw-semibold fs-5" style="color:var(--nx-text)">{{ $patient->name }}</div>
          <div class="small text-muted nx-num mb-3">RM {{ $patient->medical_record_number }} · {{ $patient->genderLabel() }} · {{ $patient->ageLabel() }} · {{ $encounter->paymentType->name }}</div>
          <x-allergy-banner :allergies="$patient->allergies" />
          <a href="{{ route('patients.show', $patient) }}#alergi" class="btn btn-sm btn-soft-secondary mt-3" target="_blank"><i class="bi bi-plus-lg"></i> Catat / ubah alergi</a>
        </div>
      </div>
      @if ($encounter->vitalSigns->isNotEmpty())
        <div class="card mt-4">
          <div class="card-header"><h5 class="mb-0">Pengukuran hari ini</h5></div>
          <ul class="list-group list-group-flush small">
            @foreach ($encounter->vitalSigns as $v)
              <li class="list-group-item"><span class="nx-num">{{ $v->recorded_at->format('H:i') }}</span> — {{ $v->summary() }}<div class="text-muted">{{ $v->recorder?->name }}</div></li>
            @endforeach
          </ul>
        </div>
      @endif
    </div>

    <div class="col-lg-8">
      <form method="POST" action="{{ route('emr.assessment.store', $encounter) }}" class="card" novalidate>
        @csrf
        <div class="card-header d-flex align-items-center">
          <h5 class="mb-0 me-auto">Tanda vital &amp; antropometri</h5>
          @if ($patient->isChild())
            <span class="badge badge-soft-warning"><i class="bi bi-exclamation-triangle-fill"></i> Pasien anak — berat badan wajib</span>
          @endif
        </div>
        <div class="card-body">
          <div class="row g-3">
            @foreach ($fields as [$name, $label, $unit, $step])
              <div class="col-6 col-md-3">
                <label class="form-label" for="{{ $name }}">{{ $label }}</label>
                <div class="input-group">
                  <input class="form-control nx-num @error($name) is-invalid @enderror" type="number" step="{{ $step }}" inputmode="decimal"
                         min="{{ \App\Modules\Emr\Models\VitalSign::RANGES[$name][0] }}" max="{{ \App\Modules\Emr\Models\VitalSign::RANGES[$name][1] }}"
                         id="{{ $name }}" name="{{ $name }}" value="{{ old($name, in_array($name, ['height']) ? $last?->{$name} : null) }}"
                         @if ($name === 'weight' && $patient->isChild()) required @endif>
                  <span class="input-group-text">{{ $unit }}</span>
                  @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
              </div>
            @endforeach
            <div class="col-12">
              <label class="form-label" for="chief_complaint">Keluhan utama / anamnesis singkat</label>
              <textarea class="form-control @error('chief_complaint') is-invalid @enderror" id="chief_complaint" name="chief_complaint" rows="3">{{ old('chief_complaint') }}</textarea>
              @error('chief_complaint')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
          </div>
        </div>
        <div class="card-footer d-flex gap-2">
          <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>Simpan asesmen</button>
          <a class="btn btn-soft-secondary" href="{{ route('emr.index') }}">Kembali</a>
        </div>
      </form>
    </div>
  </div>
@endsection
