@extends('layouts.app', ['title' => 'Pemasok'])

@section('content')
  <x-page-head title="Pemasok" subtitle="PBF / distributor asal obat, dipilih saat penerimaan barang." :crumbs="['Obat & Stok']" />

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header">
          <form class="row g-2 w-100" method="GET">
            <div class="col-sm-8"><input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Cari pemasok" aria-label="Cari pemasok"></div>
            <div class="col-auto"><button class="btn btn-soft-secondary" type="submit" data-allow-resubmit>Cari</button></div>
          </form>
        </div>
        <div class="table-responsive">
          <table class="table mb-0 nx-table-compact">
            <thead><tr><th>Pemasok</th><th>Telepon</th><th class="text-end">Penerimaan</th><th>Status</th><th></th></tr></thead>
            <tbody>
              @forelse ($suppliers as $supplier)
                <tr>
                  <td>
                    <div class="fw-semibold" style="color:var(--nx-text)">{{ $supplier->name }}</div>
                    @if ($supplier->address)<div class="small text-muted">{{ $supplier->address }}</div>@endif
                  </td>
                  <td class="nx-num small">{{ $supplier->phone ?? '—' }}</td>
                  <td class="text-end nx-num">{{ $supplier->receipts_count }}</td>
                  <td><x-status-badge :active="$supplier->is_active" /></td>
                  <td class="text-end"><button type="button" class="btn btn-sm btn-soft-secondary" data-bs-toggle="collapse" data-bs-target="#edit{{ $supplier->id }}"><i class="bi bi-pencil"></i> Ubah</button></td>
                </tr>
                <tr class="collapse" id="edit{{ $supplier->id }}">
                  <td colspan="5">
                    <form method="POST" action="{{ route('suppliers.update', $supplier) }}" class="row g-2 align-items-center">
                      @csrf @method('PUT')
                      <div class="col-md-4"><input class="form-control" name="name" value="{{ $supplier->name }}" required aria-label="Nama pemasok"></div>
                      <div class="col-md-2"><input class="form-control" name="phone" value="{{ $supplier->phone }}" placeholder="Telepon" aria-label="Telepon"></div>
                      <div class="col-md-3"><input class="form-control" name="address" value="{{ $supplier->address }}" placeholder="Alamat" aria-label="Alamat"></div>
                      <div class="col-auto">
                        <div class="form-check form-switch mb-0">
                          <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="act{{ $supplier->id }}" @checked($supplier->is_active)>
                          <label class="form-check-label small" for="act{{ $supplier->id }}">Aktif</label>
                        </div>
                      </div>
                      <div class="col-auto"><button class="btn btn-primary" type="submit">Simpan</button></div>
                    </form>
                  </td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada pemasok.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @if ($suppliers->hasPages())<div class="card-footer">{{ $suppliers->links() }}</div>@endif
      </div>
    </div>

    <div class="col-lg-4">
      <form method="POST" action="{{ route('suppliers.store') }}" class="card" novalidate>
        @csrf
        <div class="card-header"><h5 class="mb-0">Tambah pemasok</h5></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label" for="name">Nama</label>
            <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="mb-3">
            <label class="form-label" for="phone">Telepon <span class="text-muted">(opsional)</span></label>
            <input class="form-control" id="phone" name="phone" value="{{ old('phone') }}">
          </div>
          <div>
            <label class="form-label" for="address">Alamat <span class="text-muted">(opsional)</span></label>
            <input class="form-control" id="address" name="address" value="{{ old('address') }}">
          </div>
        </div>
        <div class="card-footer"><button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg me-1"></i>Tambah</button></div>
      </form>
    </div>
  </div>
@endsection
