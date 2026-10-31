@extends('layouts.app', ['title' => 'Template SOAP'])

@section('content')
  <x-page-head title="Template SOAP" subtitle="Teks cepat yang bisa disisipkan ke catatan pemeriksaan. Berlaku untuk semua dokter di faskes ini." :crumbs="['Ruang Periksa' => route('emr.index')]" />

  <div class="row g-4">
    <div class="col-lg-8">
      @foreach ($sections as $key => $label)
        @php $items = $templates->where('section', $key); @endphp
        <div class="card mb-4">
          <div class="card-header"><h5 class="mb-0">{{ $label }}</h5></div>
          <ul class="list-group list-group-flush">
            @forelse ($items as $t)
              <li class="list-group-item d-flex gap-3">
                <div class="flex-grow-1">
                  <div class="fw-semibold" style="color:var(--nx-text)">{{ $t->title }}</div>
                  <div class="small text-muted" style="white-space:pre-line">{{ $t->body }}</div>
                </div>
                <form method="POST" action="{{ route('emr.templates.destroy', $t) }}" data-confirm="Hapus template {{ $t->title }}?">
                  @csrf @method('DELETE')
                  <button class="nx-icon-btn" type="submit" aria-label="Hapus template"><i class="bi bi-trash"></i></button>
                </form>
              </li>
            @empty
              <li class="list-group-item small text-muted">Belum ada template.</li>
            @endforelse
          </ul>
        </div>
      @endforeach
    </div>
    <div class="col-lg-4">
      <form method="POST" action="{{ route('emr.templates.store') }}" class="card nx-emr-sticky" novalidate>
        @csrf
        <div class="card-header"><h5 class="mb-0">Tambah template</h5></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label" for="section">Bagian</label>
            <select class="form-select" id="section" name="section">
              @foreach ($sections as $key => $label)<option value="{{ $key }}" @selected(old('section') === $key)>{{ $label }}</option>@endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" for="title">Judul</label>
            <input class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title') }}" required>
            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div>
            <label class="form-label" for="body">Isi</label>
            <textarea class="form-control @error('body') is-invalid @enderror" id="body" name="body" rows="5" required>{{ old('body') }}</textarea>
            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
        </div>
        <div class="card-footer"><button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg me-1"></i>Tambah</button></div>
      </form>
    </div>
  </div>
@endsection
