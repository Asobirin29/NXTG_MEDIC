@extends('layouts.app', [
  'title' => 'Antrean & Pendaftaran',
  'quickAction' => ['label' => 'Daftarkan kunjungan', 'url' => route('registration.create')],
])

@section('content')
  <x-page-head title="Antrean Hari Ini" :subtitle="now()->translatedFormat('l, d F Y')">
    <a href="{{ route('registration.create') }}" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Daftarkan kunjungan</a>
  </x-page-head>

  {{-- Filter poli --}}
  <ul class="nav nav-pills gap-1 mb-3">
    <li class="nav-item"><a class="nav-link {{ request('poli_id') ? '' : 'active' }}" href="{{ route('registration.index', request()->except('poli_id')) }}">Semua poli</a></li>
    @foreach ($polis as $poli)
      <li class="nav-item"><a class="nav-link {{ request('poli_id') == $poli->id ? 'active' : '' }}" href="{{ route('registration.index', ['poli_id' => $poli->id] + request()->except('poli_id')) }}">{{ $poli->name }}</a></li>
    @endforeach
  </ul>

  {{-- Ringkasan status --}}
  <div class="d-flex flex-wrap gap-2 mb-4">
    <a href="{{ route('registration.index', request()->except('status')) }}" class="btn btn-sm {{ request('status') ? 'btn-soft-secondary' : 'btn-soft-primary' }}">
      Semua <span class="badge bg-body text-body ms-1 nx-num">{{ $counts->sum() }}</span>
    </a>
    @foreach ($statuses as $status)
      <a href="{{ route('registration.index', ['status' => $status->value] + request()->except('status')) }}"
         class="btn btn-sm {{ request('status') === $status->value ? 'btn-soft-primary' : 'btn-soft-secondary' }}">
        <i class="bi {{ $status->icon() }}"></i> {{ $status->label() }} <span class="badge bg-body text-body ms-1 nx-num">{{ $counts[$status->value] ?? 0 }}</span>
      </a>
    @endforeach
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0 nx-table-compact">
        <thead><tr><th>No.</th><th>Pasien</th><th>Poli / Dokter</th><th>Jenis bayar</th><th>Daftar</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @forelse ($encounters as $e)
            <tr class="{{ $e->status->isOpen() ? '' : 'opacity-75' }}">
              <td class="nx-queue-no fs-6">{{ $e->queueLabel() }}</td>
              <td>
                <a href="{{ route('patients.show', $e->patient) }}" class="fw-semibold text-decoration-none" style="color:var(--nx-text)">{{ $e->patient->name }}</a>
                @if ($e->patient->allergies->isNotEmpty())
                  <span class="badge badge-soft-danger ms-1" title="{{ $e->patient->allergies->map->allergenLabel()->implode(', ') }}"><i class="bi bi-exclamation-octagon-fill"></i> Alergi</span>
                @endif
                <div class="small text-muted nx-num">RM {{ $e->patient->medical_record_number }} · {{ $e->patient->ageLabel() }} · {{ $e->patient->gender }}</div>
              </td>
              <td>{{ $e->poli->name }}<div class="small text-muted">{{ $e->doctor?->name ?? 'Dokter belum ditentukan' }}</div></td>
              <td>{{ $e->paymentType->name }}</td>
              <td class="nx-num small">{{ $e->registered_at->format('H:i') }}</td>
              <td>
                <span class="badge {{ $e->status->badge() }}"><i class="bi {{ $e->status->icon() }}"></i> {{ $e->status->label() }}</span>
                @if ($e->cancel_reason)<div class="small text-muted">{{ $e->cancel_reason }}</div>@endif
              </td>
              <td class="text-end">
                @if ($e->status->canTransitionTo(\App\Modules\Patient\Enums\EncounterStatus::Cancelled))
                  <button type="button" class="btn btn-sm btn-soft-danger js-cancel" data-action="{{ route('registration.cancel', $e) }}" data-label="{{ $e->queueLabel() }} — {{ $e->patient->name }}">
                    <i class="bi bi-x-lg"></i> Batal
                  </button>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-5">
              <i class="bi bi-people d-block fs-3 mb-2"></i>Belum ada pasien di antrean.
            </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Modal pembatalan --}}
  <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelTitle" aria-hidden="true">
    <div class="modal-dialog">
      <form method="POST" class="modal-content" id="cancelForm">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="cancelTitle">Batalkan kunjungan</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2" id="cancelLabel"></p>
          <label class="form-label" for="cancelReason">Alasan</label>
          <input class="form-control" id="cancelReason" name="reason" list="cancelReasons" required maxlength="255">
          <datalist id="cancelReasons">
            <option value="Pasien tidak hadir saat dipanggil">
            <option value="Pasien membatalkan kunjungan">
            <option value="Salah pilih poli">
            <option value="Salah pilih jenis pembayaran">
          </datalist>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-soft-secondary" data-bs-dismiss="modal">Tutup</button>
          <button type="submit" class="btn btn-danger">Batalkan kunjungan</button>
        </div>
      </form>
    </div>
  </div>
@endsection

@push('scripts')
<script>
jQuery(function ($) {
  var modal = new bootstrap.Modal('#cancelModal');
  $('.js-cancel').on('click', function () {
    $('#cancelForm').attr('action', $(this).data('action'));
    $('#cancelLabel').text($(this).data('label'));
    $('#cancelReason').val('');
    modal.show();
  });
  $('#cancelModal').on('shown.bs.modal', function () { $('#cancelReason').trigger('focus'); });
});
</script>
@endpush
