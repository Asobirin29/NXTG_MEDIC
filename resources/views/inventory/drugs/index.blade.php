@extends('layouts.app', ['title' => 'Master Obat'])

@section('content')
  <x-page-head title="Master Obat" subtitle="Obat faskes beserta zat aktif, kemasan, dan golongan." :crumbs="['Obat & Stok']">
    <a href="{{ route('drugs.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah obat</a>
  </x-page-head>

  <div class="card">
    <div class="card-header">
      <form class="row g-2 align-items-center w-100" method="GET">
        <div class="col-md-5 col-lg-4">
          <input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Nama, zat aktif, KFA, atau scan barcode" aria-label="Cari obat" autofocus>
        </div>
        <div class="col-sm-4 col-md-3">
          <select class="form-select" name="category" aria-label="Filter golongan">
            <option value="">Semua golongan</option>
            @foreach ($categories as $value => $label)
              <option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-sm-4 col-md-2">
          <select class="form-select" name="status" aria-label="Filter status">
            <option value="">Aktif</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
          </select>
        </div>
        <div class="col-auto"><button class="btn btn-soft-secondary" type="submit" data-allow-resubmit>Terapkan</button></div>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0 nx-table-compact">
        <thead><tr><th>Obat</th><th>Zat aktif</th><th>Golongan</th><th>Kemasan</th><th class="text-end">Stok tersedia</th><th></th></tr></thead>
        <tbody>
          @forelse ($drugs as $drug)
            <tr>
              <td>
                <div class="fw-semibold" style="color:var(--nx-text)">{{ $drug->name }}</div>
                <div class="small text-muted">{{ ucfirst($drug->dosage_form) }}@if ($drug->kfa_code) · KFA <span class="nx-num">{{ $drug->kfa_code }}</span>@endif</div>
              </td>
              <td class="small">{{ $drug->compositionLabel() }}</td>
              <td><span class="badge {{ $drug->category->badge() }}">{{ $drug->category->label() }}</span></td>
              <td class="small">
                {{ $drug->packagings->sortByDesc('quantity_in_base')->map(fn ($p) => $p->unit_name.($p->quantity_in_base > 1 ? ' ('.$p->quantity_in_base.')' : ''))->implode(' · ') }}
              </td>
              <td class="text-end">
                <a href="{{ route('stock.show', $drug) }}" class="text-reset nx-num">{{ number_format($levels[$drug->id], 0, ',', '.') }} <span class="text-muted small">{{ $drug->base_unit }}</span></a>
                @if ($levels[$drug->id] <= $drug->min_stock)<span class="badge badge-soft-warning d-block mt-1">≤ min. {{ number_format($drug->min_stock, 0, ',', '.') }}</span>@endif
              </td>
              <td class="text-end"><a href="{{ route('drugs.edit', $drug) }}" class="btn btn-sm btn-soft-secondary"><i class="bi bi-pencil"></i> Ubah</a></td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada obat yang cocok.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($drugs->hasPages())<div class="card-footer">{{ $drugs->links() }}</div>@endif
  </div>
@endsection
