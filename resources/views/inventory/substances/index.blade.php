@extends('layouts.app', ['title' => 'Zat Aktif'])

@section('content')
  <x-page-head title="Zat Aktif" subtitle="Referensi bersama dan tambahan faskes. Dipakai untuk komposisi obat, alergi pasien, dan aturan interaksi." :crumbs="['Obat & Stok']" />

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header">
          <form class="row g-2 w-100" method="GET">
            <div class="col-sm-8"><input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Cari zat aktif" aria-label="Cari zat aktif"></div>
            <div class="col-auto"><button class="btn btn-soft-secondary" type="submit" data-allow-resubmit>Cari</button></div>
          </form>
        </div>
        <div class="table-responsive">
          <table class="table mb-0 nx-table-compact">
            <thead><tr><th>Zat aktif</th><th>ATC</th><th>Sumber</th><th class="text-end">Dipakai di obat</th><th></th></tr></thead>
            <tbody>
              @forelse ($substances as $substance)
                <tr>
                  <td class="fw-semibold" style="color:var(--nx-text)">{{ $substance->name }}</td>
                  <td class="nx-num small">{{ $substance->atc_code ?? '—' }}</td>
                  <td>
                    @if ($substance->isShared())<span class="badge badge-soft-secondary">Referensi</span>@else<span class="badge badge-soft-primary">Faskes</span>@endif
                  </td>
                  <td class="text-end nx-num">{{ $substance->drugs_count }}</td>
                  <td class="text-end">
                    @unless ($substance->isShared())
                      <button type="button" class="btn btn-sm btn-soft-secondary" data-bs-toggle="collapse" data-bs-target="#edit{{ $substance->id }}"><i class="bi bi-pencil"></i> Ubah</button>
                    @endunless
                  </td>
                </tr>
                @unless ($substance->isShared())
                  <tr class="collapse" id="edit{{ $substance->id }}">
                    <td colspan="5">
                      <form method="POST" action="{{ route('substances.update', $substance) }}" class="row g-2">
                        @csrf @method('PUT')
                        <div class="col-sm-6"><input class="form-control" name="name" value="{{ $substance->name }}" required aria-label="Nama"></div>
                        <div class="col-sm-3"><input class="form-control" name="atc_code" value="{{ $substance->atc_code }}" placeholder="ATC" aria-label="Kode ATC"></div>
                        <div class="col-auto"><button class="btn btn-primary" type="submit">Simpan</button></div>
                      </form>
                    </td>
                  </tr>
                @endunless
              @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Tidak ditemukan.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @if ($substances->hasPages())<div class="card-footer">{{ $substances->links() }}</div>@endif
      </div>
    </div>

    <div class="col-lg-4">
      <form method="POST" action="{{ route('substances.store') }}" class="card" novalidate>
        @csrf
        <div class="card-header"><h5 class="mb-0">Tambah zat aktif</h5></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label" for="name">Nama (INN, Bahasa Indonesia)</label>
            <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div>
            <label class="form-label" for="atc_code">Kode ATC <span class="text-muted">(opsional)</span></label>
            <input class="form-control nx-num" id="atc_code" name="atc_code" value="{{ old('atc_code') }}">
          </div>
          <p class="form-text mb-0 mt-3">Zat aktif tambahan hanya terlihat di faskes ini.</p>
        </div>
        <div class="card-footer"><button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg me-1"></i>Tambah</button></div>
      </form>
    </div>
  </div>
@endsection
