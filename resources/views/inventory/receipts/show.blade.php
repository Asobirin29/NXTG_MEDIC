@extends('layouts.app', ['title' => 'Penerimaan '.$receipt->number])

@section('content')
  <x-page-head :title="'Penerimaan '.$receipt->number" :subtitle="$receipt->supplier->name.' · '.$receipt->received_at->translatedFormat('d F Y')" :crumbs="['Obat & Stok', 'Penerimaan Barang' => route('receipts.index')]">
    <button type="button" class="btn btn-soft-secondary nx-no-print" onclick="window.print()"><i class="bi bi-printer me-1"></i>Cetak</button>
    <a href="{{ route('receipts.create') }}" class="btn btn-primary nx-no-print"><i class="bi bi-plus-lg me-1"></i>Penerimaan baru</a>
  </x-page-head>

  <div class="card">
    <div class="card-body row g-3 small">
      <div class="col-sm-6 col-lg-3"><div class="text-muted">Pemasok</div><div class="fw-semibold">{{ $receipt->supplier->name }}</div></div>
      <div class="col-sm-6 col-lg-3"><div class="text-muted">No. faktur</div><div class="nx-num">{{ $receipt->invoice_number ?? '—' }}</div></div>
      <div class="col-sm-6 col-lg-3"><div class="text-muted">Diterima oleh</div><div>{{ $receipt->receiver?->name ?? '—' }} · <span class="nx-num">{{ $receipt->created_at->format('d/m/Y H:i') }}</span></div></div>
      <div class="col-sm-6 col-lg-3"><div class="text-muted">Catatan</div><div>{{ $receipt->notes ?? '—' }}</div></div>
    </div>
    <div class="table-responsive">
      <table class="table mb-0 nx-table-compact">
        <thead><tr><th>Obat</th><th>Batch</th><th>ED</th><th class="text-end">Jumlah</th><th class="text-end">Satuan terkecil</th><th class="text-end">Harga / kemasan</th><th class="text-end">Subtotal</th></tr></thead>
        <tbody>
          @foreach ($receipt->items as $item)
            <tr>
              <td class="fw-semibold" style="color:var(--nx-text)">
                @can('inventory.view')<a href="{{ route('stock.show', $item->drug_id) }}" class="text-reset">{{ $item->drug_name }}</a>@else{{ $item->drug_name }}@endcan
              </td>
              <td class="nx-num">{{ $item->batch_number }}</td>
              <td><x-expiry :date="$item->expiry_date" /></td>
              <td class="text-end nx-num">{{ number_format($item->quantity, 0, ',', '.') }} {{ $item->unit_name }}</td>
              <td class="text-end nx-num">{{ number_format($item->quantity_base, 0, ',', '.') }}</td>
              <td class="text-end"><x-rupiah :value="$item->unit_price" /></td>
              <td class="text-end"><x-rupiah :value="$item->subtotal()" /></td>
            </tr>
          @endforeach
        </tbody>
        <tfoot><tr><th colspan="6" class="text-end">Total</th><th class="text-end"><x-rupiah :value="$receipt->total_cost" /></th></tr></tfoot>
      </table>
    </div>
    <div class="card-footer small text-muted"><i class="bi bi-lock"></i> Penerimaan bersifat final. Bila ada salah input, koreksi lewat penyesuaian stok di kartu stok obat terkait.</div>
  </div>
@endsection
