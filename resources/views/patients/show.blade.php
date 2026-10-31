@extends('layouts.app', ['title' => $patient->name])

<x-tom-select-assets />

@section('content')
  <x-page-head :title="$patient->name" :subtitle="'No. RM '.$patient->medical_record_number" :crumbs="['Pasien' => route('patients.index')]">
    @can('patients.manage')
      <a href="{{ route('patients.edit', $patient) }}" class="btn btn-soft-secondary"><i class="bi bi-pencil me-1"></i>Ubah data</a>
    @endcan
    @can('registration.access')
      <a href="{{ route('registration.create', ['patient' => $patient->id]) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Daftarkan kunjungan</a>
    @endcan
  </x-page-head>

  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card mb-4">
        <div class="card-body">
          <div class="d-flex align-items-center gap-3 mb-3">
            <span class="nx-avatar-initial lg">{{ mb_strtoupper(mb_substr($patient->name, 0, 1)) }}</span>
            <div>
              <div class="fw-semibold" style="color:var(--nx-text)">{{ $patient->name }}</div>
              <div class="small text-muted">{{ $patient->genderLabel() }} · {{ $patient->ageLabel() }}</div>
            </div>
          </div>
          <x-allergy-banner :allergies="$patient->allergies" />
          <dl class="row small mb-0 mt-3">
            <dt class="col-5 text-muted fw-normal">Tanggal lahir</dt><dd class="col-7">{{ $patient->birth_date->translatedFormat('d F Y') }}</dd>
            <dt class="col-5 text-muted fw-normal">NIK</dt><dd class="col-7 nx-num">{{ $patient->maskedNik() ?? '—' }}</dd>
            <dt class="col-5 text-muted fw-normal">Gol. darah</dt><dd class="col-7">{{ $patient->blood_type ?? '—' }}</dd>
            <dt class="col-5 text-muted fw-normal">No. HP</dt><dd class="col-7 nx-num">{{ $patient->phone ?? '—' }}</dd>
            <dt class="col-5 text-muted fw-normal">BPJS/asuransi</dt><dd class="col-7 nx-num">{{ $patient->insurance_number ?? '—' }}</dd>
            <dt class="col-5 text-muted fw-normal">Alamat</dt><dd class="col-7 mb-0">{{ $patient->address ?? '—' }}</dd>
          </dl>
          @unless ($patient->is_active)<div class="badge badge-soft-secondary mt-3">Pasien nonaktif</div>@endunless
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      {{-- ===== Alergi ===== --}}
      <div class="card mb-4">
        <div class="card-header" id="alergi"><h5 class="mb-0">Alergi</h5></div>
        <div class="table-responsive">
          <table class="table mb-0 nx-table-compact">
            <thead><tr><th>Penyebab</th><th>Jenis</th><th>Keparahan</th><th>Reaksi</th><th>Dicatat</th><th></th></tr></thead>
            <tbody>
              @forelse ($patient->allergies as $allergy)
                <tr>
                  <td class="fw-semibold" style="color:var(--nx-text)">{{ $allergy->allergenLabel() }}</td>
                  <td class="small">{{ $allergy->type->label() }}</td>
                  <td>@if ($allergy->severity)<span class="badge {{ $allergy->severity->badge() }}">{{ $allergy->severity->label() }}</span>@else<span class="text-muted small">—</span>@endif</td>
                  <td class="small">{{ $allergy->reaction ?? '—' }}</td>
                  <td class="small text-muted">{{ $allergy->recorder?->name }}<br>{{ $allergy->created_at->format('d/m/Y') }}</td>
                  <td class="text-end">
                    @can('allergies.manage')
                      <form method="POST" action="{{ route('patients.allergies.destroy', [$patient, $allergy]) }}" data-confirm="Hapus catatan alergi {{ $allergy->allergenLabel() }}?">
                        @csrf @method('DELETE')
                        <button class="nx-icon-btn" type="submit" aria-label="Hapus alergi"><i class="bi bi-trash"></i></button>
                      </form>
                    @endcan
                  </td>
                </tr>
              @empty
                <tr><td colspan="6" class="text-center text-muted py-3">Belum ada alergi tercatat.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @can('allergies.manage')
          <div class="card-footer">
            @if ($errors->allergy->any())
              <div class="alert alert-danger small py-2 mb-2">{{ $errors->allergy->first() }}</div>
            @endif
            <form method="POST" action="{{ route('patients.allergies.store', $patient) }}" class="row g-2 align-items-end" id="allergyForm">
              @csrf
              <div class="col-md-3">
                <label class="form-label small" for="allergy_type">Jenis</label>
                <select class="form-select" id="allergy_type" name="type">
                  @foreach ($allergyTypes as $value => $label)
                    <option value="{{ $value }}" @selected(old('type', 'drug') === $value)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-4 js-allergen-drug">
                <label class="form-label small" for="substance_id">Zat aktif</label>
                <select id="substance_id" name="substance_id" data-remote-select data-url="{{ route('substances.search') }}" placeholder="Cari zat aktif…"></select>
              </div>
              <div class="col-md-4 js-allergen-other d-none">
                <label class="form-label small" for="allergen_name">Penyebab</label>
                <input class="form-control" id="allergen_name" name="allergen_name" value="{{ old('allergen_name') }}" placeholder="mis. udang, debu">
              </div>
              <div class="col-md-2">
                <label class="form-label small" for="severity">Keparahan</label>
                <select class="form-select" id="severity" name="severity">
                  <option value="">—</option>
                  @foreach ($severities as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label small" for="reaction">Reaksi</label>
                <input class="form-control" id="reaction" name="reaction" value="{{ old('reaction') }}" placeholder="mis. gatal, sesak">
              </div>
              <div class="col-12"><button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-plus-lg me-1"></i>Catat alergi</button></div>
            </form>
          </div>
        @endcan
      </div>

      {{-- ===== Riwayat kunjungan ===== --}}
      <div class="card">
        <div class="card-header"><h5 class="mb-0">Riwayat kunjungan</h5></div>
        <div class="table-responsive">
          <table class="table mb-0 nx-table-compact">
            <thead><tr><th>Tanggal</th><th>Antrean</th><th>Poli</th><th>Dokter</th><th>Jenis bayar</th><th>Status</th></tr></thead>
            <tbody>
              @forelse ($encounters as $e)
                <tr>
                  <td class="nx-num text-nowrap">{{ $e->registered_at->format('d/m/Y H:i') }}</td>
                  <td class="nx-num">{{ $e->queueLabel() }}</td>
                  <td>{{ $e->poli->name }}</td>
                  <td>{{ $e->doctor?->name ?? '—' }}</td>
                  <td>{{ $e->paymentType->name }}</td>
                  <td><span class="badge {{ $e->status->badge() }}"><i class="bi {{ $e->status->icon() }}"></i> {{ $e->status->label() }}</span></td>
                </tr>
              @empty
                <tr><td colspan="6" class="text-center text-muted py-3">Belum ada kunjungan.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
jQuery(function ($) {
  function toggleAllergen() {
    var isDrug = $('#allergy_type').val() === 'drug';
    $('.js-allergen-drug').toggleClass('d-none', !isDrug);
    $('.js-allergen-other').toggleClass('d-none', isDrug);
  }
  $('#allergy_type').on('change', toggleAllergen);
  toggleAllergen();
});
</script>
@endpush
