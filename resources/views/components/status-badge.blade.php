@props(['active'])

@if ($active)
  <span class="badge badge-soft-success">Aktif</span>
@else
  <span class="badge badge-soft-secondary">Nonaktif</span>
@endif
