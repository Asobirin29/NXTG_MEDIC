{{-- Tanggal ED + penanda: kedaluwarsa (merah) atau ≤ 90 hari (kuning). Ikon + teks, tidak hanya warna. --}}
@props(['date'])
@php
  $date = \Illuminate\Support\Carbon::parse($date)->startOfDay();
  $days = (int) today()->diffInDays($date, false);
@endphp
<span class="nx-num">{{ $date->format('d/m/Y') }}</span>
@if ($days <= 0)
  <span class="badge badge-soft-danger ms-1"><i class="bi bi-x-octagon"></i> Kedaluwarsa</span>
@elseif ($days <= \App\Modules\Inventory\Models\Batch::NEAR_EXPIRY_DAYS)
  <span class="badge badge-soft-warning ms-1"><i class="bi bi-hourglass-split"></i> {{ $days }} hari lagi</span>
@endif
