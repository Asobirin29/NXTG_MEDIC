{{-- Filter periode laporan (satu baris di atas isi) + unduh Excel dengan filter yang sama. --}}
<div class="card mb-4">
  <div class="card-body">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-sm-4 col-md-3">
        <label class="form-label small mb-1" for="dari">Dari</label>
        <input class="form-control nx-num @error('dari') is-invalid @enderror" type="date" id="dari" name="dari" value="{{ $from->toDateString() }}">
      </div>
      <div class="col-sm-4 col-md-3">
        <label class="form-label small mb-1" for="sampai">Sampai</label>
        <input class="form-control nx-num @error('sampai') is-invalid @enderror" type="date" id="sampai" name="sampai" value="{{ $to->toDateString() }}">
      </div>
      <div class="col-auto"><button class="btn btn-primary" type="submit" data-allow-resubmit>Tampilkan</button></div>
      <div class="col-auto d-flex gap-1 flex-wrap">
        @foreach (['Bulan ini' => [today()->startOfMonth(), today()], 'Bulan lalu' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()], '7 hari' => [today()->subDays(6), today()]] as $label => [$a, $b])
          <a class="btn btn-sm btn-soft-secondary" href="{{ route('reports.show', [$report, 'dari' => $a->toDateString(), 'sampai' => $b->toDateString()]) }}">{{ $label }}</a>
        @endforeach
      </div>
      <div class="col-auto ms-auto">
        <a class="btn btn-soft-success" href="{{ route('reports.show', [$report, 'dari' => $from->toDateString(), 'sampai' => $to->toDateString(), 'unduh' => 1]) }}"><i class="bi bi-file-earmark-excel me-1"></i>Unduh Excel</a>
      </div>
    </form>
    @if ($errors->any())<div class="small text-danger mt-2">{{ $errors->first() }}</div>@endif
  </div>
</div>
