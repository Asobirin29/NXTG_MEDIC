{{-- Etiket obat (PRD §6.5): nama pasien, tanggal, nama obat, aturan pakai, kedaluwarsa. Satu etiket per obat. --}}
@extends('layouts.print', ['title' => 'Etiket '.$sale->title(), 'paper' => '60mm'])

@section('styles')
  .label { border: 1px solid #000; padding: 2mm; margin-bottom: 3mm; page-break-inside: avoid; }
  .label .signa { font-size: 15px; font-weight: 700; text-align: center; margin: 2mm 0; }
  .label .drug { font-weight: 700; font-size: 13px; }
  @media print { .label { margin-bottom: 0; page-break-after: always; } }
@endsection

@section('toolbar')
  <a href="{{ $sale->isOpen() ? route('pos.cart', $sale) : route('pos.sales.show', $sale) }}">Kembali</a>
@endsection

@section('content')
  <div class="paper">
    @forelse ($labels as $l)
      <div class="label">
        <div class="center bold">{{ $sale->tenant->name }}</div>
        @if ($sale->tenant->phone)<div class="center" style="font-size:10px">Telp {{ $sale->tenant->phone }}</div>@endif
        <hr>
        <div class="row"><span>{{ $sale->patient?->name ?? ($sale->label ?: 'Pasien') }}</span><span>{{ now()->format('d/m/Y') }}</span></div>
        @if ($sale->patient)<div style="font-size:10px">No. RM {{ $sale->patient->medical_record_number }}</div>@endif
        <div class="drug" style="margin-top:2mm">{{ $l['item']->name }}</div>
        <div>Jumlah: {{ $l['item']->quantity }} {{ $l['item']->unit_name }} ({{ $l['item']->quantity_base }} {{ $l['item']->drug->base_unit }})</div>
        <div class="signa">{{ $l['item']->signa ?: 'Sesuai petunjuk dokter / apoteker' }}</div>
        <div class="row" style="font-size:10px"><span>Batch {{ $l['batches'] ?: '—' }}</span><span>ED {{ $l['expiry']?->format('d/m/Y') ?? '—' }}</span></div>
        @if ($sale->prescription?->doctor)<div style="font-size:10px">Resep: {{ $sale->prescription->doctor->name }}</div>@endif
      </div>
    @empty
      <p>Tidak ada obat di keranjang ini.</p>
    @endforelse
  </div>
@endsection
