@props(['allergies'])

{{-- Banner merah alergi (PRD §11.3). Selalu tampil — "tidak ada alergi" juga informasi klinis. --}}
@if ($allergies->isNotEmpty())
  <div class="nx-allergy-banner" role="alert">
    <i class="bi bi-exclamation-octagon-fill"></i>
    <span><span class="text-uppercase small fw-bold me-1">Alergi:</span>{{ $allergies->map->allergenLabel()->implode(', ') }}</span>
  </div>
@else
  <div class="nx-clinical-alert minor py-2"><i class="bi bi-shield-check"></i><span>Tidak ada alergi tercatat</span></div>
@endif
