@extends('layouts.app', ['title' => 'Pengguna'])

@section('content')
  <x-page-head title="Pengguna" subtitle="Akun petugas faskes beserta role dan PIN." :crumbs="['Pengaturan']">
    <a href="{{ route('settings.users.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah pengguna</a>
  </x-page-head>

  <div class="card">
    <div class="card-header">
      <form class="row g-2 align-items-center w-100" method="GET">
        <div class="col-sm-6 col-lg-4">
          <input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama atau email" aria-label="Cari pengguna">
        </div>
        <div class="col-sm-4 col-lg-3">
          <select class="form-select" name="role" aria-label="Filter role">
            <option value="">Semua role</option>
            @foreach ($roles as $value => $label)
              <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-auto"><button class="btn btn-soft-secondary" type="submit" data-allow-resubmit>Terapkan</button></div>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0 nx-table-compact">
        <thead>
          <tr><th>Nama</th><th>Role</th><th>SIP/STR</th><th>PIN</th><th>Status</th><th>Login terakhir</th><th></th></tr>
        </thead>
        <tbody>
          @forelse ($users as $u)
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span class="nx-avatar-initial" aria-hidden="true">{{ $u->initials() }}</span>
                  <div>
                    <div class="fw-semibold" style="color:var(--nx-text)">{{ $u->name }}</div>
                    <div class="small text-muted">{{ $u->email }}</div>
                  </div>
                </div>
              </td>
              <td>{{ $u->role->label() }}</td>
              <td class="nx-num">{{ $u->license_number ?? '—' }}</td>
              <td>
                @if ($u->hasPin())
                  <span class="badge badge-soft-success"><i class="bi bi-check2"></i> Ada</span>
                @else
                  <span class="badge badge-soft-secondary">Belum</span>
                @endif
              </td>
              <td>
                @if ($u->is_active)
                  <span class="badge badge-soft-success">Aktif</span>
                @else
                  <span class="badge badge-soft-danger">Nonaktif</span>
                @endif
              </td>
              <td class="small text-muted">{{ $u->last_login_at?->translatedFormat('d M Y H:i') ?? '—' }}</td>
              <td class="text-end">
                <a href="{{ route('settings.users.edit', $u) }}" class="btn btn-sm btn-soft-secondary"><i class="bi bi-pencil"></i> Ubah</a>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada pengguna yang cocok.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($users->hasPages())
      <div class="card-footer">{{ $users->links() }}</div>
    @endif
  </div>
@endsection
