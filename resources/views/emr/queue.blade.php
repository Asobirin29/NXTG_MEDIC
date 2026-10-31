@extends('layouts.app', ['title' => 'Ruang Periksa'])

@use('App\Modules\Patient\Enums\EncounterStatus')

@section('content')
  <x-page-head title="Antrean Poli" :subtitle="now()->translatedFormat('l, d F Y')" />

  <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <ul class="nav nav-pills gap-1 me-auto">
      <li class="nav-item"><a class="nav-link {{ $tab === 'aktif' ? 'active' : '' }}" href="{{ route('emr.index', ['tab' => 'aktif'] + request()->except('tab')) }}">Menunggu &amp; diperiksa</a></li>
      <li class="nav-item"><a class="nav-link {{ $tab === 'selesai' ? 'active' : '' }}" href="{{ route('emr.index', ['tab' => 'selesai'] + request()->except('tab')) }}">Selesai diperiksa</a></li>
    </ul>
    <form method="GET" class="d-flex gap-2 align-items-center">
      <input type="hidden" name="tab" value="{{ $tab }}">
      <select class="form-select form-select-sm" name="poli_id" onchange="this.form.submit()" aria-label="Filter poli">
        <option value="">Semua poli</option>
        @foreach ($polis as $poli)
          <option value="{{ $poli->id }}" @selected(request('poli_id') == $poli->id)>{{ $poli->name }}</option>
        @endforeach
      </select>
      @if ($isDoctor)
        <input type="hidden" name="saya" value="0">
        <div class="form-check form-switch text-nowrap mb-0">
          <input class="form-check-input" type="checkbox" role="switch" id="saya" name="saya" value="1" @checked($mine) onchange="this.form.submit()">
          <label class="form-check-label small" for="saya">Pasien saya</label>
        </div>
      @endif
    </form>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0 nx-table-compact">
        <thead><tr><th>No.</th><th>Pasien</th><th>Tanda vital</th><th>Keluhan awal</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @forelse ($encounters as $e)
            @php $v = $e->latestVital; @endphp
            <tr>
              <td class="nx-queue-no fs-6">{{ $e->queueLabel() }}</td>
              <td>
                <div class="fw-semibold" style="color:var(--nx-text)">{{ $e->patient->name }}</div>
                <div class="small text-muted nx-num">RM {{ $e->patient->medical_record_number }} · {{ $e->patient->ageLabel() }} · {{ $e->patient->gender }} · {{ $e->paymentType->name }}</div>
                @if ($e->patient->allergies->isNotEmpty())
                  <span class="badge badge-soft-danger mt-1"><i class="bi bi-exclamation-octagon-fill"></i> Alergi: {{ $e->patient->allergies->map->allergenLabel()->implode(', ') }}</span>
                @endif
              </td>
              <td class="small nx-num text-nowrap">
                @if ($v)
                  {{ $v->bloodPressure() ? 'TD '.$v->bloodPressure() : '' }}
                  {{ $v->temperature ? ' · '.str_replace('.', ',', $v->temperature).'°C' : '' }}
                  @if ($v->weight)<span class="{{ $e->patient->isChild() ? 'badge badge-soft-warning' : '' }}"> · BB {{ str_replace('.', ',', $v->weight) }} kg</span>@endif
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td class="small" style="max-width:260px">{{ \Illuminate\Support\Str::limit($v?->chief_complaint, 80) ?: '—' }}</td>
              <td>
                <span class="badge {{ $e->status->badge() }}"><i class="bi {{ $e->status->icon() }}"></i> {{ $e->status->label() }}</span>
                @if ($e->doctor)<div class="small text-muted">{{ $e->doctor->name }}</div>@endif
              </td>
              <td class="text-end text-nowrap">
                @if (in_array($e->status, [EncounterStatus::Queued, EncounterStatus::NurseAssessed], true))
                  @can('emr.vitals')
                    <a href="{{ route('emr.assessment', $e) }}" class="btn btn-sm {{ $e->status === EncounterStatus::Queued && ! $isDoctor ? 'btn-primary' : 'btn-soft-secondary' }}"><i class="bi bi-heart-pulse"></i> Asesmen</a>
                  @endcan
                @endif
                @can('emr.soap')
                  <a href="{{ route('emr.consultation', $e) }}" class="btn btn-sm {{ $e->status->isOpen() && $e->status !== EncounterStatus::Queued ? 'btn-primary' : 'btn-soft-secondary' }}">
                    <i class="bi bi-clipboard2-pulse"></i> {{ $e->status === EncounterStatus::InConsultation ? 'Lanjutkan' : ($tab === 'aktif' ? 'Periksa' : 'Lihat') }}
                  </a>
                @endcan
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-cup-hot d-block fs-3 mb-2"></i>Tidak ada pasien di daftar ini.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
