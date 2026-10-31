{{-- Variabel: $vital (VitalSign|null), $patient --}}
@if ($vital)
  <div class="nx-vitals">
    <div><span>TD</span><strong>{{ $vital->bloodPressure() ?? '—' }}</strong><small>mmHg</small></div>
    <div><span>Nadi</span><strong>{{ $vital->heart_rate ?? '—' }}</strong><small>x/mnt</small></div>
    <div><span>RR</span><strong>{{ $vital->respiratory_rate ?? '—' }}</strong><small>x/mnt</small></div>
    <div><span>Suhu</span><strong>{{ $vital->temperature !== null ? str_replace('.', ',', $vital->temperature) : '—' }}</strong><small>°C</small></div>
    <div><span>SpO₂</span><strong>{{ $vital->spo2 ?? '—' }}</strong><small>%</small></div>
    <div class="{{ $patient->isChild() ? 'nx-vital-key' : '' }}">
      <span>BB{{ $patient->isChild() ? ' (anak)' : '' }}</span><strong>{{ $vital->weight !== null ? str_replace('.', ',', $vital->weight) : '—' }}</strong><small>kg</small>
    </div>
    <div><span>TB</span><strong>{{ $vital->height !== null ? str_replace('.', ',', $vital->height) : '—' }}</strong><small>cm</small></div>
    <div><span>IMT</span><strong>{{ $vital->bmi() !== null ? str_replace('.', ',', $vital->bmi()) : '—' }}</strong><small>kg/m²</small></div>
  </div>
  <div class="small text-muted mt-2">{{ $vital->recorder?->name }} · {{ $vital->recorded_at->format('H:i') }}</div>
@else
  <div class="nx-clinical-alert moderate"><i class="bi bi-exclamation-triangle-fill"></i><div><span class="nx-ca-label d-block">Belum ada tanda vital</span>Asesmen perawat belum dilakukan.</div></div>
@endif
