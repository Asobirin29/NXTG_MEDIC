@extends('layouts.app', ['title' => 'Impor Data'])

@section('content')
  <x-page-head title="Impor Data" subtitle="Pindahkan data dari Excel/CSV. File diperiksa dulu; tidak ada yang disimpan sebelum Anda konfirmasi." :crumbs="['Administrasi']" />

  <div class="row g-4">
    <div class="col-xl-7">
      <div class="card">
        <div class="card-header pb-0">
          <ul class="nav nav-tabs card-header-tabs" role="tablist">
            @foreach ($types as $key => $importer)
              <li class="nav-item" role="presentation">
                <a class="nav-link @if ($key === $selected) active @endif" href="{{ route('imports.index', ['jenis' => $key]) }}">{{ $importer->label() }}</a>
              </li>
            @endforeach
          </ul>
        </div>
        @php $importer = $types[$selected]; @endphp
        <div class="card-body">
          @if ($importer->description())<p class="small text-muted">{{ $importer->description() }}</p>@endif

          <form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data" class="mb-4" novalidate>
            @csrf
            <input type="hidden" name="type" value="{{ $selected }}">
            <label class="form-label" for="file">File {{ $importer->label() }} (.xlsx atau .csv, maks. 10 MB / {{ number_format(\App\Modules\Import\Support\Spreadsheet::MAX_ROWS, 0, ',', '.') }} baris)</label>
            <div class="input-group">
              <input class="form-control @error('file') is-invalid @enderror" type="file" id="file" name="file" accept=".xlsx,.csv" required>
              <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Periksa file</button>
            </div>
            @error('file')<div class="small text-danger mt-1">{{ $message }}</div>@enderror
            <div class="mt-2"><a href="{{ route('imports.template', $selected) }}"><i class="bi bi-download me-1"></i>Unduh templat {{ $importer->label() }} (.xlsx)</a></div>
          </form>

          <h6 class="mb-2">Kolom</h6>
          <div class="table-responsive">
            <table class="table table-sm nx-table-compact mb-0">
              <thead><tr><th>Kolom</th><th>Keterangan</th><th>Contoh</th></tr></thead>
              <tbody>
                @foreach ($importer->columns() as $name => [$required, $note, $example])
                  <tr>
                    <td class="nx-num text-nowrap">{{ $name }}@if ($required)<span class="text-danger">*</span>@endif</td>
                    <td class="small">{{ $note }}</td>
                    <td class="small nx-num">{{ $example ?? '—' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          <p class="small text-muted mt-2 mb-0"><span class="text-danger">*</span> wajib. Baris pertama file harus berisi judul kolom. CSV boleh dipisah koma atau titik koma.</p>
        </div>
      </div>
    </div>

    <div class="col-xl-5">
      <div class="card">
        <div class="card-header"><h5 class="mb-0">Riwayat impor</h5></div>
        <ul class="list-group list-group-flush">
          @forelse ($history as $item)
            <a class="list-group-item list-group-item-action" href="{{ route('imports.show', $item) }}">
              <div class="d-flex justify-content-between gap-2">
                <span class="fw-semibold text-truncate">{{ $item->original_name }}</span>
                <span class="badge {{ $item->statusBadge() }}">{{ $item->statusLabel() }}</span>
              </div>
              <div class="small text-muted">
                {{ $types[$item->type]->label() }} · {{ $item->created_at->format('d/m/Y H:i') }} · {{ $item->user?->name }}
                · {{ $item->status === 'completed' ? $item->imported_rows.' diimpor' : $item->valid_rows.' valid' }}@if ($item->error_rows), {{ $item->error_rows }} bermasalah @endif
              </div>
            </a>
          @empty
            <li class="list-group-item small text-muted">Belum ada impor.</li>
          @endforelse
        </ul>
      </div>
    </div>
  </div>
@endsection
