@extends('layouts.app', ['title' => 'Interaksi Obat'])

@section('content')
  <x-page-head title="Interaksi Obat" subtitle="Aturan pasangan zat aktif yang dicek otomatis saat dokter meresepkan dan saat obat diserahkan." :crumbs="['Data Klinis']">
    <form method="POST" action="{{ route('interactions.sync') }}" class="d-inline">
      @csrf
      <button class="btn btn-soft-secondary" type="submit"><i class="bi bi-arrow-repeat me-1"></i>Ambil aturan baru dari template</button>
    </form>
    <a href="{{ route('interactions.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah aturan</a>
  </x-page-head>

  <div class="nx-clinical-alert moderate mb-4" role="note">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div>
      <span class="nx-ca-label d-block">Alat bantu keputusan klinis</span>
      Template awal wajib ditinjau apoteker penanggung jawab faskes. Sistem tidak menggantikan penilaian tenaga kesehatan.
    </div>
  </div>

  <div class="row g-4 mb-4">
    @foreach (\App\Modules\Cds\Enums\Severity::cases() as $sev)
      <div class="col-sm-4">
        <a class="card text-decoration-none" href="{{ route('interactions.index', ['severity' => $sev->value]) }}">
          <div class="card-body d-flex align-items-center gap-3">
            <span class="nx-icon-sq {{ ['minor' => 'primary', 'moderate' => 'warning', 'major' => 'danger'][$sev->value] }}"><i class="bi {{ $sev->icon() }}"></i></span>
            <div><div class="small text-muted">{{ $sev->label() }}</div><div class="h4 mb-0 nx-num">{{ $counts[$sev->value] ?? 0 }}</div></div>
          </div>
        </a>
      </div>
    @endforeach
  </div>

  <div class="card">
    <div class="card-header">
      <form class="row g-2 align-items-center w-100" method="GET">
        <div class="col-sm-6 col-lg-4"><input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Cari zat aktif" aria-label="Cari zat aktif"></div>
        <div class="col-sm-4 col-lg-3">
          <select class="form-select" name="severity" aria-label="Filter keparahan">
            <option value="">Semua keparahan</option>
            @foreach ($severities as $value => $label)
              <option value="{{ $value }}" @selected(request('severity') === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-auto"><button class="btn btn-soft-secondary" type="submit" data-allow-resubmit>Terapkan</button></div>
      </form>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0 nx-table-compact">
        <thead><tr><th>Pasangan zat aktif</th><th>Keparahan</th><th>Dampak & saran</th><th>Sumber</th><th></th></tr></thead>
        <tbody>
          @forelse ($rules as $rule)
            <tr class="{{ $rule->is_active ? '' : 'opacity-50' }}">
              <td class="fw-semibold text-nowrap" style="color:var(--nx-text)">{{ $rule->substanceA->name }} <span class="text-muted">+</span> {{ $rule->substanceB->name }}</td>
              <td class="text-nowrap"><span class="badge {{ $rule->severity->badge() }}"><i class="bi {{ $rule->severity->icon() }}"></i> {{ $rule->severity->label() }}</span></td>
              <td class="small" style="min-width:280px">
                {{ $rule->effect }}
                @if ($rule->recommendation)<div class="text-muted">Saran: {{ $rule->recommendation }}</div>@endif
              </td>
              <td class="small text-nowrap">
                {{ $rule->template_id ? 'Template v'.$rule->template_version : 'Faskes' }}
                @unless ($rule->is_active)<div><span class="badge badge-soft-secondary">Nonaktif</span></div>@endunless
              </td>
              <td class="text-end"><a href="{{ route('interactions.edit', $rule) }}" class="btn btn-sm btn-soft-secondary"><i class="bi bi-pencil"></i> Ubah</a></td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada aturan yang cocok.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($rules->hasPages())<div class="card-footer">{{ $rules->links() }}</div>@endif
  </div>
@endsection
