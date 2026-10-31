@extends('layouts.app', ['title' => 'Pasien'])

@section('content')
  <x-page-head title="Pasien" subtitle="Cari dengan nama, No. RM, NIK (16 digit), nomor HP, atau nomor BPJS.">
    @can('patients.manage')
      <a href="{{ route('patients.create') }}" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Pasien baru</a>
    @endcan
  </x-page-head>

  <div class="card">
    <div class="card-header">
      <form class="row g-2 w-100" method="GET">
        <div class="col-md-6 col-lg-5">
          <input class="form-control" type="search" name="q" value="{{ $term }}" placeholder="Nama, No. RM, NIK, HP, atau No. BPJS" aria-label="Cari pasien" autofocus>
        </div>
        <div class="col-auto"><button class="btn btn-soft-secondary" type="submit" data-allow-resubmit><i class="bi bi-search me-1"></i>Cari</button></div>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0 nx-table-compact">
        <thead><tr><th>No. RM</th><th>Nama</th><th>Usia / JK</th><th>NIK</th><th>Kontak</th><th>Alergi</th><th></th></tr></thead>
        <tbody>
          @forelse ($patients as $p)
            <tr>
              <td class="nx-num fw-semibold">{{ $p->medical_record_number }}</td>
              <td>
                <a href="{{ route('patients.show', $p) }}" class="fw-semibold text-decoration-none" style="color:var(--nx-text)">{{ $p->name }}</a>
                @unless ($p->is_active)<span class="badge badge-soft-secondary ms-1">Nonaktif</span>@endunless
              </td>
              <td class="text-nowrap">{{ $p->ageLabel() }} · {{ $p->gender }}</td>
              <td class="nx-num small">{{ $p->maskedNik() ?? '—' }}</td>
              <td class="small">{{ $p->phone ?? '—' }}</td>
              <td>
                @if ($p->allergies_count)
                  <span class="badge badge-soft-danger"><i class="bi bi-exclamation-octagon-fill"></i> {{ $p->allergies_count }}</span>
                @else
                  <span class="text-muted small">—</span>
                @endif
              </td>
              <td class="text-end text-nowrap">
                @can('registration.access')
                  <a href="{{ route('registration.create', ['patient' => $p->id]) }}" class="btn btn-sm btn-soft-primary"><i class="bi bi-plus-lg"></i> Daftarkan</a>
                @endcan
                <a href="{{ route('patients.show', $p) }}" class="btn btn-sm btn-soft-secondary">Detail</a>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-4">{{ $term ? 'Pasien tidak ditemukan.' : 'Belum ada pasien.' }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($patients->hasPages())<div class="card-footer">{{ $patients->links() }}</div>@endif
  </div>
@endsection
