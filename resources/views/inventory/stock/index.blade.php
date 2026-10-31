@extends('layouts.app', ['title' => 'Stok & Batch'])

@section('content')
  <x-page-head title="Stok & Batch" subtitle="Stok tersedia = batch layak jual yang belum kedaluwarsa, dalam satuan terkecil." :crumbs="['Obat & Stok']">
    @can('inventory.manage')
      <a href="{{ route('stock.opname') }}" class="btn btn-soft-secondary"><i class="bi bi-clipboard-check me-1"></i>Stok opname</a>
      <a href="{{ route('receipts.create') }}" class="btn btn-primary"><i class="bi bi-box-arrow-in-down me-1"></i>Terima barang</a>
    @endcan
  </x-page-head>

  <div class="row g-4 mb-4">
    @include('inventory.stock.partials.kpis')
    <div class="col-sm-6 col-xl-3">
      <a href="{{ route('stock.index') }}" class="card nx-kpi nx-card-lift h-100 text-decoration-none @if (! $filter) border-primary @endif">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start"><span class="nx-kpi-label">Nilai persediaan</span><span class="nx-icon-sq primary"><i class="bi bi-box-seam"></i></span></div>
          <div class="nx-kpi-value fs-4">@if (isset($stockValue))<x-rupiah :value="$stockValue" />@else Semua obat @endif</div>
          <div class="nx-kpi-foot"><span>{{ isset($stockValue) ? 'harga beli, batch layak jual' : 'tampilkan seluruh obat' }}</span></div>
        </div>
      </a>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <form class="row g-2 align-items-center w-100" method="GET">
        @if ($filter)<input type="hidden" name="filter" value="{{ $filter }}">@endif
        <div class="col-md-5 col-lg-4">
          <input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="{{ isset($batches) ? 'Cari obat atau nomor batch' : 'Nama, zat aktif, atau scan barcode' }}" aria-label="Cari">
        </div>
        <div class="col-auto"><button class="btn btn-soft-secondary" type="submit" data-allow-resubmit>Cari</button></div>
        <div class="col-auto ms-auto small text-muted">
          @if ($filter)
            Menampilkan: <span class="fw-semibold">{{ $filters[$filter] }}</span> · <a href="{{ route('stock.index') }}">semua obat</a>
          @endif
        </div>
      </form>
    </div>

    @isset($batches)
      {{-- Daftar batch (ED dekat / kedaluwarsa) --}}
      <div class="table-responsive">
        <table class="table table-hover mb-0 nx-table-compact">
          <thead><tr><th>Obat</th><th>Batch</th><th>ED</th><th class="text-end">Saldo</th><th>Pemasok</th><th></th></tr></thead>
          <tbody>
            @forelse ($batches as $batch)
              <tr>
                <td class="fw-semibold" style="color:var(--nx-text)">{{ $batch->drug->name }}</td>
                <td class="nx-num">{{ $batch->batch_number }}</td>
                <td><x-expiry :date="$batch->expiry_date" /></td>
                <td class="text-end"><x-stock-qty :qty="$batch->qty_on_hand" :drug="$batch->drug" /></td>
                <td class="small">{{ $batch->supplier?->name ?? '—' }}</td>
                <td class="text-end text-nowrap"><a class="btn btn-sm btn-soft-secondary" href="{{ route('stock.show', $batch->drug) }}">Kartu stok</a></td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted py-4">
                {{ $filter === 'expired' ? 'Tidak ada batch kedaluwarsa yang masih bersaldo.' : 'Tidak ada batch dengan ED ≤ 90 hari.' }}
              </td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if ($filter === 'expired' && $batches->isNotEmpty())
        <div class="card-footer small text-muted"><i class="bi bi-info-circle"></i> Batch kedaluwarsa otomatis tidak dialokasikan. Catat pemusnahannya lewat <span class="fw-semibold">Kartu stok → Sesuaikan → Kedaluwarsa (dimusnahkan)</span>.</div>
      @endif
    @else
      {{-- Daftar obat --}}
      <div class="table-responsive">
        <table class="table table-hover mb-0 nx-table-compact">
          <thead><tr><th>Obat</th><th class="text-end">Stok tersedia</th><th class="text-end">Ambang min.</th><th>ED terdekat</th><th class="text-end">Batch</th><th></th></tr></thead>
          <tbody>
            @forelse ($drugs as $drug)
              @php $n = $nearest[$drug->id] ?? null; @endphp
              <tr>
                <td>
                  <div class="fw-semibold" style="color:var(--nx-text)">{{ $drug->name }}</div>
                  @if ($drug->available_stock === 0)
                    <span class="badge badge-soft-danger"><i class="bi bi-x-circle"></i> Habis</span>
                  @elseif ($drug->available_stock <= $drug->min_stock)
                    <span class="badge badge-soft-warning"><i class="bi bi-graph-down-arrow"></i> Menipis</span>
                  @endif
                </td>
                <td class="text-end"><x-stock-qty :qty="$drug->available_stock" :drug="$drug" /></td>
                <td class="text-end nx-num">{{ number_format($drug->min_stock, 0, ',', '.') }} <span class="small text-muted">{{ $drug->base_unit }}</span></td>
                <td>@if ($n)<x-expiry :date="$n->nearest" />@else<span class="text-muted">—</span>@endif</td>
                <td class="text-end nx-num">{{ $n->batch_count ?? 0 }}</td>
                <td class="text-end text-nowrap"><a class="btn btn-sm btn-soft-secondary" href="{{ route('stock.show', $drug) }}">Kartu stok</a></td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted py-4">{{ $filter === 'low' ? 'Tidak ada obat di bawah ambang minimum.' : 'Tidak ada obat yang cocok.' }}</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if ($drugs instanceof \Illuminate\Contracts\Pagination\Paginator && $drugs->hasPages())<div class="card-footer">{{ $drugs->links() }}</div>@endif
    @endisset
  </div>
@endsection
