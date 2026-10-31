@forelse ($encounter->diagnoses as $dx)
  <div class="d-flex align-items-center gap-2 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
    <span class="badge {{ $dx->isPrimary() ? 'badge-soft-primary' : 'badge-soft-secondary' }}" style="min-width:72px">{{ $dx->isPrimary() ? 'Primer' : 'Sekunder' }}</span>
    <span class="nx-num fw-semibold" style="color:var(--nx-text)">{{ $dx->icd10->code }}</span>
    <span class="flex-grow-1 small">{{ $dx->icd10->name_id }}</span>
    @if ($editable)
      <button type="button" class="nx-icon-btn js-remove-item" data-url="{{ route('emr.diagnoses.destroy', [$encounter, $dx]) }}" data-target="#diagnosisList" aria-label="Hapus diagnosis {{ $dx->icd10->code }}"><i class="bi bi-x-lg"></i></button>
    @endif
  </div>
@empty
  <div class="text-muted small py-2">Belum ada diagnosis.</div>
@endforelse
