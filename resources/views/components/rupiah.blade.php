@props(['value'])
<span class="nx-num">{{ $value === null ? '—' : 'Rp'.number_format($value, 0, ',', '.') }}</span>
