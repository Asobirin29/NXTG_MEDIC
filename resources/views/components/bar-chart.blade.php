{{--
  Grafik kolom satu seri (tanpa legenda; judul kartu menamai seri).
  Kolom ≤ 24px, ujung data membulat 4px, garis bantu hairline, tooltip per kolom
  (hover & fokus keyboard), nilai tertinggi diberi label, tabel tersembunyi untuk pembaca layar.
  $points: list<array{label: string, value: int|float, tooltip?: string}>
--}}
@props(['points', 'format' => 'number', 'height' => 180, 'label' => 'Grafik'])
@php
  $fmt = fn ($v) => $format === 'rupiah'
      ? 'Rp'.number_format($v, 0, ',', '.')
      : number_format($v, 0, ',', '.');
  $short = function ($v) use ($format) {
      $prefix = $format === 'rupiah' ? 'Rp' : '';
      return match (true) {
          $v >= 1_000_000 => $prefix.rtrim(rtrim(number_format($v / 1_000_000, 1, ',', '.'), '0'), ',').' jt',
          $v >= 1_000 => $prefix.rtrim(rtrim(number_format($v / 1_000, 1, ',', '.'), '0'), ',').' rb',
          default => $prefix.number_format($v, 0, ',', '.'),
      };
  };
  $max = max(1, collect($points)->max('value'));
  // Puncak sumbu dibulatkan ke angka "bersih" yang separuhnya juga bulat (2, 4, 6, 8, 10 × 10^n).
  $mag = 10 ** floor(log10($max));
  $top = max(2, collect([1, 2, 4, 6, 8, 10])->map(fn ($m) => $m * $mag)->first(fn ($v) => $v >= $max));
  $peakIndex = collect($points)->search(fn ($p) => $p['value'] == collect($points)->max('value'));
@endphp
<figure class="nx-bars mb-0" role="img" aria-label="{{ $label }}">
  <div class="nx-bars-plot" style="height: {{ $height }}px">
    <div class="nx-bars-grid" aria-hidden="true">
      <span style="bottom:100%"><em>{{ $short($top) }}</em></span>
      <span style="bottom:50%"><em>{{ $short($top / 2) }}</em></span>
      <span style="bottom:0"><em>0</em></span>
    </div>
    <div class="nx-bars-cols">
      @foreach ($points as $i => $p)
        @php $h = $p['value'] > 0 ? max(2, $p['value'] / $top * 100) : 0; @endphp
        <div @class(['nx-bars-col', 'tip-start' => $i < 2, 'tip-end' => $i >= count($points) - 2]) tabindex="0" aria-label="{{ $p['label'] }}: {{ $fmt($p['value']) }}">
          <div class="nx-bars-bar" style="height: {{ $h }}%">
            @if ($i === $peakIndex && $p['value'] > 0)<span class="nx-bars-peak">{{ $short($p['value']) }}</span>@endif
          </div>
          <div class="nx-bars-tip" role="tooltip">
            <strong>{{ $fmt($p['value']) }}</strong>
            <span>{{ $p['tooltip'] ?? $p['label'] }}</span>
          </div>
        </div>
      @endforeach
    </div>
  </div>
  <div class="nx-bars-x" aria-hidden="true">
    @foreach ($points as $p)<span>{{ $p['label'] }}</span>@endforeach
  </div>
  <table class="visually-hidden">
    <caption>{{ $label }}</caption>
    @foreach ($points as $p)<tr><th scope="row">{{ $p['tooltip'] ?? $p['label'] }}</th><td>{{ $fmt($p['value']) }}</td></tr>@endforeach
  </table>
</figure>
