{{--
  Peringatan berjenjang (PRD §11.1): ringan = catatan kecil, sedang = banner
  kuning, berat = kartu merah yang menahan proses sampai ada alasan tertulis.
  Tingkat dibedakan dengan ikon + label teks, tidak hanya warna.
--}}
@php $hasOverride = collect($alerts)->contains(fn ($a) => $a->requiresOverride()); @endphp

@if (empty($alerts))
  <div class="nx-clinical-alert minor"><i class="bi bi-shield-check"></i><span>Tidak ada peringatan alergi, interaksi, atau duplikasi.</span></div>
@endif

@foreach ($alerts as $alert)
  @php $cls = ['minor' => 'minor', 'moderate' => 'moderate', 'major' => 'major'][$alert->severity->value]; @endphp
  <div class="nx-clinical-alert {{ $cls }} mb-2" data-alert-key="{{ $alert->key }}" role="{{ $alert->requiresOverride() ? 'alert' : 'note' }}">
    <i class="bi {{ $alert->severity->icon() }}"></i>
    <div class="flex-grow-1 min-w-0">
      <span class="nx-ca-label d-block">{{ $alert->severity->label() }} · {{ $alert->typeLabel() }}</span>
      <div class="fw-semibold">{{ $alert->title }}</div>
      <div class="small">{{ $alert->message }}</div>
      @if ($alert->recommendation)<div class="small text-muted mt-1">Saran: {{ $alert->recommendation }}</div>@endif

      @if (! $editable && ($ov = ($overrides ?? collect())->get($alert->key)))
        <div class="small mt-2 p-2 rounded-2" style="background:var(--nx-card-bg)">
          <span class="fw-semibold">Tetap diresepkan:</span> {{ $ov->reason }}
          <div class="text-muted">{{ $ov->user?->name }} · {{ $ov->created_at->format('d/m/Y H:i') }}</div>
        </div>
      @endif

      @if ($alert->requiresOverride() && $editable)
        <label class="form-label small fw-semibold mt-2 mb-1" for="ov_{{ md5($alert->key) }}">Alasan klinis bila tetap diresepkan</label>
        <textarea class="form-control form-control-sm js-override @error('overrides.'.$alert->key) is-invalid @enderror" rows="2"
                  id="ov_{{ md5($alert->key) }}" name="overrides[{{ $alert->key }}]" form="soapForm" data-key="{{ $alert->key }}"
                  placeholder="Wajib diisi, atau ganti obatnya">{{ old('overrides.'.$alert->key) }}</textarea>
        @error('overrides.'.$alert->key)<div class="invalid-feedback">{{ $message }}</div>@enderror
      @endif
    </div>
  </div>
@endforeach

@if ($hasOverride && $editable)
  <div class="small text-muted"><i class="bi bi-info-circle"></i> Alasan override dicatat atas nama Anda beserta waktu dan aturan yang dilewati.</div>
@endif
