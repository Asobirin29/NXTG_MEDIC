{{-- Struk thermal 58/80 mm (PRD §6.5). Lebar dipilih lewat ?w=58. --}}
@php
  $paper = request('w') === '58' ? '58mm' : '80mm';
  $rp = fn ($v) => number_format($v, 0, ',', '.');
@endphp
@extends('layouts.print', ['title' => 'Struk '.$sale->number, 'paper' => $paper])

@section('toolbar')
  <a href="{{ request()->fullUrlWithQuery(['w' => 80]) }}" @class(['on' => $paper === '80mm'])>80 mm</a>
  <a href="{{ request()->fullUrlWithQuery(['w' => 58]) }}" @class(['on' => $paper === '58mm'])>58 mm</a>
  <a href="{{ route('pos.sales.show', $sale) }}">Kembali</a>
@endsection

@section('content')
  <div class="paper">
    <div class="center bold">{{ $sale->tenant->name }}</div>
    @if ($sale->tenant->address)<div class="center">{{ $sale->tenant->address }}</div>@endif
    @if ($sale->tenant->phone)<div class="center">Telp {{ $sale->tenant->phone }}</div>@endif
    <hr>
    <div class="row"><span>{{ $sale->number }}</span><span>{{ $sale->completed_at?->format('d/m/Y H:i') }}</span></div>
    <div>Kasir: {{ $sale->cashier?->name }}</div>
    @if ($sale->patient)<div>Pasien: {{ $sale->patient->name }} ({{ $sale->patient->medical_record_number }})</div>@endif
    <div>Jenis bayar: {{ $sale->paymentType->name }}</div>
    @if ($sale->status === 'voided')<div class="center bold">*** VOID ***</div>@endif
    <hr>
    <table>
      @foreach ($sale->items as $item)
        <tr><td colspan="2">{{ $item->name }}</td></tr>
        <tr>
          <td>&nbsp;{{ $item->quantity }} {{ $item->unit_name ?? 'x' }}{{ $item->isDrug() ? ' ('.$item->quantity_base.')' : '' }}</td>
          <td class="right">{{ $rp($item->line_total) }}</td>
        </tr>
      @endforeach
    </table>
    <hr>
    <div class="row"><span>Total nilai</span><span>{{ $rp($sale->total_value) }}</span></div>
    @unless ($sale->paymentType->patient_pays)
      <div class="row"><span>Ditanggung {{ $sale->paymentType->name }}</span><span>-{{ $rp($sale->total_value) }}</span></div>
    @endunless
    <div class="row bold"><span>TAGIHAN</span><span>Rp{{ $rp($sale->amount_due) }}</span></div>
    @if ($sale->amount_due > 0)
      <div class="row"><span>{{ $sale->payment_method === 'cash' ? 'Tunai' : 'Non-tunai' }}</span><span>{{ $rp($sale->paid_amount) }}</span></div>
      <div class="row"><span>Kembali</span><span>{{ $rp($sale->change_amount) }}</span></div>
    @endif
    <hr>
    <div class="center">Terima kasih. Semoga lekas sembuh.</div>
    <div class="center">Simpan struk untuk retur obat.</div>
  </div>
@endsection
