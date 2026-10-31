@extends('layouts.app', ['title' => 'Jenis Pembayaran'])

@section('content')
  <x-page-head title="Jenis Pembayaran" subtitle="Dipilih sekali di awal kunjungan; menentukan tarif tindakan dan harga obat di kasir." :crumbs="['Pengaturan']">
    <a href="{{ route('settings.payment-types.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah jenis</a>
  </x-page-head>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0 nx-table-compact">
        <thead><tr><th style="width:70px">Urut</th><th style="width:140px">Kode</th><th>Nama</th><th>Tagihan ke pasien</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @foreach ($types as $type)
            <tr>
              <td class="nx-num text-muted">{{ $type->sort_order }}</td>
              <td class="nx-num">{{ $type->code }}</td>
              <td class="fw-semibold" style="color:var(--nx-text)">{{ $type->name }}</td>
              <td>
                @if ($type->patient_pays)
                  <span class="badge badge-soft-primary">Pasien membayar</span>
                @else
                  <span class="badge badge-soft-info">Rp0 (nilai tetap dicatat)</span>
                @endif
              </td>
              <td><x-status-badge :active="$type->is_active" /></td>
              <td class="text-end"><a href="{{ route('settings.payment-types.edit', $type) }}" class="btn btn-sm btn-soft-secondary"><i class="bi bi-pencil"></i> Ubah</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endsection
