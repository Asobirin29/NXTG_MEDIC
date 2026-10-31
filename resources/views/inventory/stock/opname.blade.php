@extends('layouts.app', ['title' => 'Stok Opname'])

@section('content')
  <x-page-head title="Stok Opname" subtitle="Isi hasil hitung fisik per batch. Baris yang dikosongkan tidak ikut dihitung." :crumbs="['Obat & Stok', 'Stok & Batch' => route('stock.index')]" />

  <div class="row g-4">
    <div class="col-xl-9">
      <div class="card mb-3">
        <div class="card-header">
          <form class="row g-2 w-100" method="GET">
            <div class="col-sm-7 col-md-6"><input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Saring obat (nama, zat aktif, barcode)" aria-label="Saring obat"></div>
            <div class="col-auto"><button class="btn btn-soft-secondary" type="submit" data-allow-resubmit>Saring</button></div>
          </form>
        </div>

        <form method="POST" action="{{ route('stock.opname.store') }}" id="opnameForm" novalidate>
          @csrf
          @error('counts')<div class="alert alert-danger m-3 mb-0">{{ $message }}</div>@enderror
          <div class="table-responsive">
            <table class="table mb-0 nx-table-compact align-middle">
              <thead><tr><th>Obat</th><th>Batch</th><th>ED</th><th class="text-end">Saldo sistem</th><th style="width:11rem">Hitung fisik</th><th class="text-end">Selisih</th></tr></thead>
              <tbody>
                @forelse ($batches as $batch)
                  @php $key = "counts.{$batch->id}.counted"; @endphp
                  <tr>
                    <td class="fw-semibold" style="color:var(--nx-text)">{{ $batch->drug->name }}</td>
                    <td class="nx-num small">{{ $batch->batch_number }}</td>
                    <td class="small"><x-expiry :date="$batch->expiry_date" /></td>
                    <td class="text-end nx-num">{{ number_format($batch->qty_on_hand, 0, ',', '.') }} <span class="small text-muted">{{ $batch->drug->base_unit }}</span></td>
                    <td>
                      <input type="hidden" name="counts[{{ $batch->id }}][system]" value="{{ $batch->qty_on_hand }}">
                      <div class="input-group input-group-sm" style="min-width:8.5rem">
                        <input class="form-control nx-num js-count @error($key) is-invalid @enderror" type="number" min="0" inputmode="numeric"
                               name="counts[{{ $batch->id }}][counted]" value="{{ old("counts.{$batch->id}.counted") }}" data-system="{{ $batch->qty_on_hand }}"
                               aria-label="Hitung fisik {{ $batch->drug->name }} batch {{ $batch->batch_number }}">
                        <span class="input-group-text">{{ $batch->drug->base_unit }}</span>
                      </div>
                    </td>
                    <td class="text-end nx-num js-diff">—</td>
                  </tr>
                @empty
                  <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada batch bersaldo yang cocok.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
          @if ($batches->isNotEmpty())
            <div class="card-footer">
              <div class="row g-3 align-items-end">
                <div class="col-md-7">
                  <label class="form-label" for="reason">Keterangan opname</label>
                  <input class="form-control @error('reason') is-invalid @enderror" id="reason" name="reason" value="{{ old('reason') }}" required placeholder="mis. Opname akhir bulan Oktober, rak A–C">
                  @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-5 text-md-end">
                  <div class="small text-muted mb-2"><span id="countedN">0</span> batch diisi · <span id="diffN">0</span> berselisih</div>
                  <button class="btn btn-primary" type="submit"><i class="bi bi-check2-square me-1"></i>Simpan hasil opname</button>
                </div>
              </div>
            </div>
          @endif
        </form>
      </div>
    </div>

    <div class="col-xl-3">
      <div class="card">
        <div class="card-header"><h5 class="mb-0">Riwayat opname</h5></div>
        <ul class="list-group list-group-flush">
          @forelse ($history as $o)
            <li class="list-group-item small">
              <div class="d-flex justify-content-between"><span class="nx-num fw-semibold">{{ $o->number }}</span><span class="text-muted">{{ $o->created_at->format('d/m/Y') }}</span></div>
              <div>{{ $o->reason }}</div>
              <div class="text-muted">{{ $o->batches_counted }} dihitung · {{ $o->batches_changed }} disesuaikan · {{ $o->user?->name }}</div>
            </li>
          @empty
            <li class="list-group-item small text-muted">Belum ada opname.</li>
          @endforelse
        </ul>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
jQuery(function ($) {
  function refresh() {
    var counted = 0, diffs = 0;
    $('.js-count').each(function () {
      var $cell = $(this).closest('tr').find('.js-diff');
      if (this.value === '') { $cell.text('—').removeClass('text-danger text-success'); return; }
      counted++;
      var d = parseInt(this.value, 10) - parseInt($(this).data('system'), 10);
      if (d !== 0) diffs++;
      $cell.text(d > 0 ? '+' + d : (d < 0 ? '−' + Math.abs(d) : '0'))
        .toggleClass('text-danger', d < 0).toggleClass('text-success', d > 0);
    });
    $('#countedN').text(counted);
    $('#diffN').text(diffs);
  }
  $(document).on('input', '.js-count', refresh);
  refresh();
});
</script>
@endpush
