{{-- Rekam medis satu kunjungan (read-only): dipakai di riwayat & setelah dikunci. --}}
@php $note = $encounter->clinicalNote; @endphp

<dl class="mb-3">
  @foreach (\App\Modules\Emr\Models\ClinicalNote::SECTIONS as $field => $label)
    <dt class="small text-muted fw-semibold">{{ $label }}</dt>
    <dd class="mb-2" style="white-space:pre-line">{{ $note?->{$field} ?: '—' }}</dd>
  @endforeach
</dl>

<div class="small text-muted fw-semibold mb-1">Diagnosis</div>
<div class="mb-3">@include('emr.partials.diagnoses', ['editable' => false])</div>

@if ($encounter->prescription?->items->isNotEmpty())
  <div class="small text-muted fw-semibold mb-1">Resep</div>
  <div class="mb-3">@include('emr.partials.prescription-items', ['editable' => false])</div>
@endif

<div class="small text-muted fw-semibold mb-1">Tindakan</div>
<div class="mb-3">@include('emr.partials.services', ['editable' => false])</div>

@if ($note?->isLocked())
  <div class="small text-muted"><i class="bi bi-lock-fill"></i> Dikunci {{ $note->locked_at->format('d/m/Y H:i') }} oleh {{ $note->doctor?->name ?? '—' }}</div>
@endif

@if ($note && $note->addenda->isNotEmpty())
  <div class="mt-3">
    <div class="small text-muted fw-semibold mb-1">Addendum</div>
    @foreach ($note->addenda as $add)
      <div class="border-start border-3 ps-2 mb-2" style="border-color:var(--nx-warning)!important">
        <div class="small" style="white-space:pre-line">{{ $add->content }}</div>
        <div class="small text-muted">{{ $add->author?->name }} · {{ $add->created_at->format('d/m/Y H:i') }}</div>
      </div>
    @endforeach
  </div>
@endif
