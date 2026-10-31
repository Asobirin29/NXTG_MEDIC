@extends('layouts.app', ['title' => 'Daftarkan Kunjungan'])

@section('content')
  <x-page-head title="Daftarkan Kunjungan" :crumbs="['Antrean' => route('registration.index')]" />

  @if (! $patient)
    {{-- ===== Langkah 1: cari pasien ===== --}}
    <div class="row justify-content-center">
      <div class="col-xl-8">
        <div class="card">
          <div class="card-body">
            <form method="GET" class="d-flex gap-2 mb-3">
              <input class="form-control form-control-lg" type="search" name="q" value="{{ $term }}" autofocus
                     placeholder="Nama, No. RM, NIK, No. HP, atau No. BPJS" aria-label="Cari pasien">
              <button class="btn btn-primary btn-lg" type="submit" data-allow-resubmit><i class="bi bi-search"></i><span class="d-none d-sm-inline ms-1">Cari</span></button>
            </form>

            @if ($term !== '')
              <div class="list-group mb-3">
                @forelse ($results as $p)
                  <a class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3" href="{{ route('registration.create', ['patient' => $p->id]) }}">
                    <span class="nx-avatar-initial">{{ mb_strtoupper(mb_substr($p->name, 0, 1)) }}</span>
                    <span class="flex-grow-1">
                      <span class="fw-semibold d-block" style="color:var(--nx-text)">{{ $p->name }}</span>
                      <span class="small text-muted nx-num">RM {{ $p->medical_record_number }} · {{ $p->ageLabel() }} · {{ $p->gender }} · lahir {{ $p->birth_date->format('d/m/Y') }}</span>
                    </span>
                    <i class="bi bi-chevron-right text-muted"></i>
                  </a>
                @empty
                  <div class="text-center text-muted py-4">Pasien tidak ditemukan.</div>
                @endforelse
              </div>
            @endif

            @can('patients.manage')
              <a href="{{ route('patients.create', ['next' => 'register', 'q' => $term]) }}" class="btn btn-soft-primary w-100">
                <i class="bi bi-person-plus me-1"></i>Pasien baru
              </a>
            @endcan
          </div>
        </div>
      </div>
    </div>
  @else
    {{-- ===== Langkah 2: pilih poli & jenis bayar ===== --}}
    <div class="row g-4">
      <div class="col-lg-4">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center gap-3 mb-3">
              <span class="nx-avatar-initial lg">{{ mb_strtoupper(mb_substr($patient->name, 0, 1)) }}</span>
              <div>
                <div class="fw-semibold" style="color:var(--nx-text)">{{ $patient->name }}</div>
                <div class="small text-muted nx-num">RM {{ $patient->medical_record_number }} · {{ $patient->genderLabel() }} · {{ $patient->ageLabel() }}</div>
              </div>
            </div>
            <x-allergy-banner :allergies="$patient->allergies" />
            <dl class="row small mb-0 mt-3">
              <dt class="col-5 text-muted fw-normal">BPJS/asuransi</dt><dd class="col-7 nx-num" id="insuranceNumber">{{ $patient->insurance_number ?? '—' }}</dd>
              <dt class="col-5 text-muted fw-normal">No. HP</dt><dd class="col-7 nx-num mb-0">{{ $patient->phone ?? '—' }}</dd>
            </dl>
            @if ($todayVisits->isNotEmpty())
              <div class="alert alert-info small mt-3 mb-0">
                Kunjungan hari ini:
                @foreach ($todayVisits as $v){{ $v->queueLabel() }} ({{ $v->poli->name }}, {{ $v->status->label() }})@if (! $loop->last), @endif @endforeach
              </div>
            @endif
            <div class="d-flex gap-2 mt-3">
              <a href="{{ route('registration.create') }}" class="btn btn-sm btn-soft-secondary"><i class="bi bi-arrow-left"></i> Ganti pasien</a>
              @can('patients.manage')
                <a href="{{ route('patients.edit', $patient) }}" class="btn btn-sm btn-soft-secondary"><i class="bi bi-pencil"></i> Ubah data</a>
              @endcan
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <form method="POST" action="{{ route('registration.store') }}" class="card" novalidate>
          @csrf
          <input type="hidden" name="patient_id" value="{{ $patient->id }}">
          <div class="card-body">
            @if ($errors->any())
              <div class="alert alert-danger small">{{ $errors->first() }}</div>
            @endif

            <span class="form-label d-block">Poli tujuan</span>
            <div class="row g-2 mb-4">
              @foreach ($polis as $poli)
                <div class="col-sm-6 col-xl-4">
                  <input type="radio" class="btn-check" name="poli_id" id="poli_{{ $poli->id }}" value="{{ $poli->id }}"
                         @checked(old('poli_id', $polis->count() === 1 ? $poli->id : null) == $poli->id) required>
                  <label class="btn btn-outline-secondary w-100 py-3" for="poli_{{ $poli->id }}"><i class="bi bi-hospital me-1"></i>{{ $poli->name }}</label>
                </div>
              @endforeach
            </div>

            <span class="form-label d-block">Jenis pembayaran</span>
            <div class="row g-2 mb-2">
              @foreach ($paymentTypes as $type)
                <div class="col-6 col-xl-3">
                  <input type="radio" class="btn-check js-payment" name="payment_type_id" id="pay_{{ $type->id }}" value="{{ $type->id }}" data-code="{{ $type->code }}"
                         @checked(old('payment_type_id', $loop->first ? $type->id : null) == $type->id) required>
                  <label class="btn btn-outline-secondary w-100 py-3" for="pay_{{ $type->id }}">{{ $type->name }}</label>
                </div>
              @endforeach
            </div>
            <div class="nx-clinical-alert moderate mb-4 d-none" id="insuranceWarning" role="status">
              <i class="bi bi-exclamation-triangle-fill"></i>
              <div><span class="nx-ca-label d-block">Nomor peserta kosong</span>Pasien belum punya nomor BPJS/asuransi. Lengkapi data pasien bila diperlukan untuk klaim.</div>
            </div>

            <label class="form-label" for="doctor_id">Dokter <span class="text-muted">(opsional)</span></label>
            <select class="form-select" id="doctor_id" name="doctor_id">
              <option value="">Dokter jaga yang bertugas</option>
              @foreach ($doctors as $doctor)
                <option value="{{ $doctor->id }}" @selected(old('doctor_id') == $doctor->id)>{{ $doctor->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="card-footer d-flex gap-2">
            <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-ticket-perforated me-1"></i>Masukkan ke antrean</button>
            <a class="btn btn-soft-secondary btn-lg" href="{{ route('registration.index') }}">Batal</a>
          </div>
        </form>
      </div>
    </div>
  @endif
@endsection

@if ($patient)
  @push('scripts')
  <script>
  jQuery(function ($) {
    var hasInsurance = {{ $patient->insurance_number ? 'true' : 'false' }};
    function check() {
      var code = $('.js-payment:checked').data('code');
      $('#insuranceWarning').toggleClass('d-none', hasInsurance || !(code === 'BPJS' || code === 'ASURANSI'));
    }
    $('.js-payment').on('change', check);
    check();
  });
  </script>
  @endpush
@endif
