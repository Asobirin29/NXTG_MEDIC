@extends('layouts.app', ['title' => 'Impor · '.$import->original_name])

@if (in_array($import->status, ['queued', 'processing'], true))
  @push('styles')<meta http-equiv="refresh" content="5">@endpush
@endif

@section('content')
  <x-page-head :title="$importer->label()" :subtitle="$import->original_name.' · diunggah '.$import->created_at->format('d/m/Y H:i').' oleh '.$import->user?->name" :crumbs="['Administrasi', 'Impor Data' => route('imports.index', ['jenis' => $import->type])]">
    <span class="badge {{ $import->statusBadge() }} fs-6">{{ $import->statusLabel() }}</span>
  </x-page-head>

  @error('import')<div class="alert alert-danger">{{ $message }}</div>@enderror

  <div class="row g-4 mb-4">
    @foreach ([['Total baris', $import->total_rows, 'bi-list-ol', 'primary'], ['Valid', $import->valid_rows, 'bi-check2-circle', 'success'], ['Bermasalah (dilewati)', $import->error_rows, 'bi-exclamation-triangle', 'danger'], ['Tersimpan', $import->status === 'completed' ? $import->imported_rows : '—', 'bi-database-check', 'info']] as [$label, $value, $icon, $tone])
      <div class="col-sm-6 col-xl-3">
        <div class="card nx-kpi h-100"><div class="card-body">
          <div class="d-flex justify-content-between align-items-start"><span class="nx-kpi-label">{{ $label }}</span><span class="nx-icon-sq {{ $tone }}"><i class="bi {{ $icon }}"></i></span></div>
          <div class="nx-kpi-value nx-num">{{ is_int($value) ? number_format($value, 0, ',', '.') : $value }}</div>
        </div></div>
      </div>
    @endforeach
  </div>

  @if ($import->status === 'previewed')
    <div class="card mb-4 border-primary">
      <div class="card-body d-flex flex-wrap gap-3 align-items-center">
        <div class="me-auto">
          <div class="fw-semibold">Belum ada data yang disimpan.</div>
          <div class="small text-muted">
            @if ($import->valid_rows)
              {{ $import->valid_rows }} baris valid akan disimpan@if ($import->error_rows); {{ $import->error_rows }} baris bermasalah dilewati — perbaiki lalu unggah ulang khusus baris tersebut@endif.
            @else
              Semua baris bermasalah. Perbaiki file lalu unggah ulang.
            @endif
          </div>
        </div>
        <form method="POST" action="{{ route('imports.destroy', $import) }}">@csrf @method('DELETE')
          <button class="btn btn-soft-secondary" type="submit">Batalkan</button>
        </form>
        @if ($import->valid_rows)
          <form method="POST" action="{{ route('imports.confirm', $import) }}" data-confirm="Simpan {{ $import->valid_rows }} baris valid?">@csrf
            <button class="btn btn-primary" type="submit"><i class="bi bi-database-add me-1"></i>Impor {{ $import->valid_rows }} baris valid</button>
          </form>
        @endif
      </div>
    </div>
  @elseif ($import->status === 'failed')
    <div class="alert alert-danger">Impor gagal dan tidak ada data yang tersimpan: {{ $import->failure_message }}</div>
  @elseif ($import->status !== 'completed')
    <div class="alert alert-info"><span class="spinner-border spinner-border-sm me-2"></span>Sedang diproses di latar belakang. Halaman ini memperbarui diri setiap 5 detik.</div>
  @endif

  @if ($import->errors)
    <div class="card mb-4">
      <div class="card-header d-flex align-items-center">
        <h5 class="mb-0 me-auto text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Kesalahan per baris</h5>
        <a class="btn btn-sm btn-soft-secondary" href="{{ route('imports.errors', $import) }}"><i class="bi bi-download me-1"></i>Unduh (.xlsx)</a>
      </div>
      <div class="table-responsive" style="max-height:420px">
        <table class="table table-sm mb-0 nx-table-compact">
          <thead><tr><th style="width:6rem">Baris</th><th>Kesalahan</th></tr></thead>
          <tbody>
            @foreach ($import->errors as $line => $messages)
              <tr><td class="nx-num">{{ $line }}</td><td class="small">@foreach ($messages as $m)<div>{{ $m }}</div>@endforeach</td></tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @if ($import->error_rows > count($import->errors))<div class="card-footer small text-muted">Menampilkan {{ count($import->errors) }} dari {{ $import->error_rows }} baris bermasalah.</div>@endif
    </div>
  @endif

  @if ($import->preview && $import->status === 'previewed')
    @php $cols = array_keys($importer->columns()); @endphp
    <div class="card">
      <div class="card-header"><h5 class="mb-0">Pratinjau baris valid</h5></div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 nx-table-compact">
          <thead><tr><th>Baris</th>@foreach ($cols as $c)<th class="text-nowrap">{{ $c }}</th>@endforeach</tr></thead>
          <tbody>
            @foreach ($import->preview as $line => $row)
              <tr><td class="nx-num">{{ $line }}</td>@foreach ($cols as $c)<td class="small">{{ $row[$c] ?? '' }}</td>@endforeach</tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @if ($import->valid_rows > count($import->preview))<div class="card-footer small text-muted">{{ count($import->preview) }} dari {{ $import->valid_rows }} baris valid ditampilkan.</div>@endif
    </div>
  @endif
@endsection
