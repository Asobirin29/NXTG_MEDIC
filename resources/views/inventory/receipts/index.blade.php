@extends('layouts.app', ['title' => 'Penerimaan Barang'])

@section('content')
  <x-page-head title="Penerimaan Barang" subtitle="Riwayat obat masuk dari pemasok. Penerimaan yang tersimpan bersifat final." :crumbs="['Obat & Stok']">
    <a href="{{ route('suppliers.index') }}" class="btn btn-soft-secondary"><i class="bi bi-truck me-1"></i>Pemasok</a>
    <a href="{{ route('receipts.create') }}" class="btn btn-primary"><i class="bi bi-box-arrow-in-down me-1"></i>Terima barang</a>
  </x-page-head>

  <div class="card">
    <div class="card-header">
      <form class="row g-2 w-100" method="GET">
        <div class="col-md-5 col-lg-4"><input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="No. penerimaan, faktur, atau pemasok" aria-label="Cari penerimaan"></div>
        <div class="col-auto"><button class="btn btn-soft-secondary" type="submit" data-allow-resubmit>Cari</button></div>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0 nx-table-compact">
        <thead><tr><th>No.</th><th>Tanggal</th><th>Pemasok</th><th>Faktur</th><th class="text-end">Item</th><th class="text-end">Total</th><th>Petugas</th><th></th></tr></thead>
        <tbody>
          @forelse ($receipts as $r)
            <tr>
              <td class="nx-num fw-semibold">{{ $r->number }}</td>
              <td class="nx-num">{{ $r->received_at->format('d/m/Y') }}</td>
              <td>{{ $r->supplier->name }}</td>
              <td class="nx-num small">{{ $r->invoice_number ?? '—' }}</td>
              <td class="text-end nx-num">{{ $r->items_count }}</td>
              <td class="text-end"><x-rupiah :value="$r->total_cost" /></td>
              <td class="small">{{ $r->receiver?->name ?? '—' }}</td>
              <td class="text-end"><a class="btn btn-sm btn-soft-secondary" href="{{ route('receipts.show', $r) }}">Lihat</a></td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada penerimaan barang.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($receipts->hasPages())<div class="card-footer">{{ $receipts->links() }}</div>@endif
  </div>
@endsection
