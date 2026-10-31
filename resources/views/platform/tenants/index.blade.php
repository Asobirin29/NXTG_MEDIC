@extends('layouts.platform', ['title' => 'Faskes'])

@section('content')
  <div class="nx-page-head d-flex flex-wrap align-items-end gap-3">
    <div class="me-auto">
      <h1>Faskes</h1>
      <p class="text-muted mb-0">Tenant puskesmas dan klinik yang berlangganan.</p>
    </div>
    <a href="{{ route('platform.tenants.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Faskes baru</a>
  </div>

  <div class="row g-4 mb-4">
    @foreach ([['Total faskes', $stats['total'], 'bi-hospital', 'primary'], ['Aktif', $stats['active'], 'bi-check-circle', 'success'], ['Ditangguhkan', $stats['suspended'], 'bi-pause-circle', 'danger']] as [$label, $value, $icon, $tone])
      <div class="col-sm-4">
        <div class="card"><div class="card-body d-flex align-items-center gap-3">
          <span class="nx-icon-sq {{ $tone }}"><i class="bi {{ $icon }}"></i></span>
          <div><div class="small text-muted">{{ $label }}</div><div class="h3 mb-0 nx-num">{{ $value }}</div></div>
        </div></div>
      </div>
    @endforeach
  </div>

  <div class="card">
    <div class="card-header">
      <form class="row g-2 align-items-center w-100" method="GET">
        <div class="col-sm-6 col-lg-4"><input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama atau kode" aria-label="Cari faskes"></div>
        <div class="col-sm-4 col-lg-3">
          <select class="form-select" name="status" aria-label="Filter status">
            <option value="">Semua status</option>
            <option value="active" @selected(request('status') === 'active')>Aktif</option>
            <option value="suspended" @selected(request('status') === 'suspended')>Ditangguhkan</option>
          </select>
        </div>
        <div class="col-auto"><button class="btn btn-soft-secondary" type="submit" data-allow-resubmit>Terapkan</button></div>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0 nx-table-compact">
        <thead><tr><th>Faskes</th><th>Kode</th><th>Jenis</th><th>Paket</th><th>Pengguna</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @forelse ($tenants as $t)
            <tr>
              <td><div class="fw-semibold" style="color:var(--nx-text)">{{ $t->name }}</div><div class="small text-muted">{{ $t->address }}</div></td>
              <td class="nx-num">{{ $t->code }}</td>
              <td class="text-capitalize">{{ $t->type }}</td>
              <td>{{ \App\Modules\Identity\Http\Controllers\Platform\TenantController::PLANS[$t->plan] ?? $t->plan }}</td>
              <td class="nx-num">{{ $t->users_count }}</td>
              <td>
                @if ($t->isActive())<span class="badge badge-soft-success">Aktif</span>@else<span class="badge badge-soft-danger">Ditangguhkan</span>@endif
              </td>
              <td class="text-end"><a href="{{ route('platform.tenants.edit', $t) }}" class="btn btn-sm btn-soft-secondary"><i class="bi bi-pencil"></i> Ubah</a></td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada faskes.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($tenants->hasPages())<div class="card-footer">{{ $tenants->links() }}</div>@endif
  </div>
@endsection
