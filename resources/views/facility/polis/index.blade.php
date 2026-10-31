@extends('layouts.app', ['title' => 'Poli'])

@section('content')
  <x-page-head title="Poli" subtitle="Unit layanan tujuan pendaftaran pasien." :crumbs="['Pengaturan']">
    <a href="{{ route('settings.polis.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah poli</a>
  </x-page-head>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0 nx-table-compact">
        <thead><tr><th style="width:140px">Kode</th><th>Nama</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @forelse ($polis as $poli)
            <tr>
              <td class="nx-num">{{ $poli->code }}</td>
              <td class="fw-semibold" style="color:var(--nx-text)">{{ $poli->name }}</td>
              <td><x-status-badge :active="$poli->is_active" /></td>
              <td class="text-end"><a href="{{ route('settings.polis.edit', $poli) }}" class="btn btn-sm btn-soft-secondary"><i class="bi bi-pencil"></i> Ubah</a></td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-muted py-4">Belum ada poli.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@endsection
