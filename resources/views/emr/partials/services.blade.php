@forelse ($encounter->services as $item)
  <div class="d-flex align-items-center gap-2 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
    <span class="flex-grow-1 small"><span class="fw-semibold" style="color:var(--nx-text)">{{ $item->service->name }}</span> <span class="text-muted nx-num">× {{ $item->quantity }}</span></span>
    <x-rupiah :value="$item->subtotal()" />
    @if ($editable)
      <button type="button" class="nx-icon-btn js-remove-item" data-url="{{ route('emr.services.destroy', [$encounter, $item]) }}" data-target="#serviceList" aria-label="Hapus tindakan {{ $item->service->name }}"><i class="bi bi-x-lg"></i></button>
    @endif
  </div>
  @if ($loop->last)
    <div class="d-flex justify-content-between pt-2 small fw-semibold"><span>Total tindakan</span><x-rupiah :value="$encounter->services->sum->subtotal()" /></div>
  @endif
@empty
  <div class="text-muted small py-2">Belum ada tindakan.</div>
@endforelse
