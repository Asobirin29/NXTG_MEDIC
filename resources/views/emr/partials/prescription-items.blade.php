@php $items = $encounter->prescription?->items ?? collect(); @endphp

@forelse ($items as $item)
  <div class="nx-rx-item">
    <div class="d-flex align-items-start gap-2">
      <span class="nx-rx-no nx-num">R/{{ $loop->iteration }}</span>
      <div class="flex-grow-1 min-w-0">
        <div class="fw-semibold" style="color:var(--nx-text)">{{ $item->drug_name }}</div>
        <div class="small nx-num">
          {{ $item->quantity }} {{ $item->unit_name }}
          @if ($item->quantity_in_base_per_unit > 1)<span class="text-muted">({{ number_format($item->quantity_base, 0, ',', '.') }} {{ $item->drug?->base_unit }})</span>@endif
        </div>
        <div class="small"><i class="bi bi-signpost-2 text-muted"></i> S. {{ $item->signa }}</div>
        @if ($item->note)<div class="small text-muted">{{ $item->note }}</div>@endif
      </div>
      @if ($editable)
        <button type="button" class="nx-icon-btn js-rx-remove" data-url="{{ route('emr.prescription.destroy', [$encounter, $item]) }}" aria-label="Hapus {{ $item->drug_name }} dari resep"><i class="bi bi-x-lg"></i></button>
      @endif
    </div>
  </div>
@empty
  <div class="text-muted small py-2">Belum ada obat di resep.</div>
@endforelse

@if (! $editable && $encounter->prescription && ! $encounter->prescription->isDraft())
  <div class="mt-2"><span class="badge {{ $encounter->prescription->status->badge() }}">{{ $encounter->prescription->status->label() }}</span></div>
@endif
