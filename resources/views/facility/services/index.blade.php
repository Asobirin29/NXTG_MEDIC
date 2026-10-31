@extends('layouts.app', ['title' => 'Tindakan Medis'])

@section('content')
  <x-page-head title="Tindakan Medis" subtitle="Master jasa/tindakan poli beserta tarif per jenis pembayaran." :crumbs="['Pengaturan']">
    <a href="{{ route('settings.services.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah tindakan</a>
  </x-page-head>

  <div class="card">
    <div class="card-header">
      <form class="row g-2 align-items-center w-100" method="GET">
        <div class="col-sm-6 col-lg-4"><input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama atau kode" aria-label="Cari tindakan"></div>
        <div class="col-sm-4 col-lg-3">
          <select class="form-select" name="poli_id" aria-label="Filter poli">
            <option value="">Semua poli</option>
            @foreach ($polis as $poli)
              <option value="{{ $poli->id }}" @selected(request('poli_id') == $poli->id)>{{ $poli->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-auto"><button class="btn btn-soft-secondary" type="submit" data-allow-resubmit>Terapkan</button></div>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0 nx-table-compact">
        <thead>
          <tr>
            <th>Tindakan</th><th>Poli</th>
            @foreach ($paymentTypes as $type)<th class="text-end">{{ $type->name }}</th>@endforeach
            <th>Status</th><th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($services as $service)
            <tr>
              <td><div class="fw-semibold" style="color:var(--nx-text)">{{ $service->name }}</div><div class="small text-muted nx-num">{{ $service->code }}</div></td>
              <td>{{ $service->poli?->name ?? 'Semua poli' }}</td>
              @foreach ($paymentTypes as $type)<td class="text-end"><x-rupiah :value="$service->priceFor($type)" /></td>@endforeach
              <td><x-status-badge :active="$service->is_active" /></td>
              <td class="text-end"><a href="{{ route('settings.services.edit', $service) }}" class="btn btn-sm btn-soft-secondary"><i class="bi bi-pencil"></i> Ubah</a></td>
            </tr>
          @empty
            <tr><td colspan="{{ 4 + $paymentTypes->count() }}" class="text-center text-muted py-4">Belum ada tindakan.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($services->hasPages())<div class="card-footer">{{ $services->links() }}</div>@endif
  </div>
@endsection
