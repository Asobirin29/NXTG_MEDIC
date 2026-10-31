@extends('layouts.app', ['title' => 'Kartu Stok · '.$drug->name])

@section('content')
  <x-page-head :title="$drug->name" :subtitle="$drug->compositionLabel().' · '.$drug->category->label()" :crumbs="['Obat & Stok', 'Stok & Batch' => route('stock.index')]">
    @can('inventory.manage')
      <a href="{{ route('drugs.edit', $drug) }}" class="btn btn-soft-secondary"><i class="bi bi-pencil me-1"></i>Master obat</a>
    @endcan
  </x-page-head>

  <div class="row g-4 mb-4">
    <div class="col-md-4">
      <div class="card nx-kpi h-100"><div class="card-body">
        <span class="nx-kpi-label">Stok tersedia</span>
        <div class="nx-kpi-value fs-4"><x-stock-qty :qty="$available" :drug="$drug" /></div>
        <div class="nx-kpi-foot">
          @if ($available === 0)
            <span class="badge badge-soft-danger"><i class="bi bi-x-circle"></i> Habis</span>
          @elseif ($available <= $drug->min_stock)
            <span class="badge badge-soft-warning"><i class="bi bi-graph-down-arrow"></i> Menipis</span>
          @endif
          <span>ambang min. {{ number_format($drug->min_stock, 0, ',', '.') }} {{ $drug->base_unit }}</span>
        </div>
      </div></div>
    </div>
    <div class="col-md-8">
      <div class="card h-100"><div class="card-body small">
        <div class="text-muted fw-semibold mb-1">Kemasan</div>
        {{ $drug->packagings->sortByDesc('quantity_in_base')->map(fn ($p) => $p->unit_name.($p->quantity_in_base > 1 ? ' = '.$p->quantity_in_base.' '.$drug->base_unit : ''))->implode(' · ') }}
        <div class="text-muted mt-2"><i class="bi bi-info-circle"></i> Pengeluaran otomatis mengambil batch dengan ED terdekat (FEFO). Batch kedaluwarsa dan karantina tidak pernah dialokasikan.</div>
      </div></div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
      <h5 class="mb-0 me-auto">Batch</h5>
      <a class="small" href="{{ route('stock.show', ['drug' => $drug, 'kosong' => $showEmpty ? null : 1]) }}">{{ $showEmpty ? 'Sembunyikan batch kosong' : 'Tampilkan batch kosong' }}</a>
    </div>
    <div class="table-responsive">
      <table class="table mb-0 nx-table-compact">
        <thead><tr><th>Urutan FEFO</th><th>Batch</th><th>ED</th><th class="text-end">Saldo</th><th class="text-end">HPP / {{ $drug->base_unit }}</th><th>Pemasok</th><th></th></tr></thead>
        <tbody>
          @php $rank = 0; @endphp
          @forelse ($batches as $batch)
            @php $usable = ! $batch->isQuarantined() && ! $batch->isExpired() && $batch->qty_on_hand > 0; @endphp
            <tr @class(['text-muted' => ! $usable])>
              <td>
                @if ($usable)
                  <span class="badge {{ ++$rank === 1 ? 'badge-soft-primary' : 'badge-soft-secondary' }}">{{ $rank === 1 ? 'Keluar berikutnya' : '#'.$rank }}</span>
                @elseif ($batch->isQuarantined())
                  <span class="badge badge-soft-danger"><i class="bi bi-slash-circle"></i> Karantina</span>
                @elseif ($batch->isExpired())
                  <span class="badge badge-soft-danger"><i class="bi bi-lock"></i> Diblokir</span>
                @else
                  <span class="badge badge-soft-secondary">Kosong</span>
                @endif
              </td>
              <td class="nx-num">{{ $batch->batch_number }}</td>
              <td><x-expiry :date="$batch->expiry_date" /></td>
              <td class="text-end"><x-stock-qty :qty="$batch->qty_on_hand" :drug="$drug" /></td>
              <td class="text-end nx-num">Rp{{ number_format((float) $batch->purchase_price_per_base, 2, ',', '.') }}</td>
              <td class="small">{{ $batch->supplier?->name ?? '—' }}</td>
              <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-soft-secondary" href="{{ route('stock.show', ['drug' => $drug, 'batch' => $batch->id]) }}#kartu">Mutasi</a>
                @can('inventory.manage')
                  <button type="button" class="btn btn-sm btn-soft-warning js-adjust" data-bs-toggle="modal" data-bs-target="#adjustModal"
                          data-url="{{ route('stock.adjust', $batch) }}" data-batch="{{ $batch->batch_number }}" data-qty="{{ $batch->qty_on_hand }}">
                    <i class="bi bi-sliders"></i> Sesuaikan
                  </button>
                @endcan
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-4">
              Belum ada batch bersaldo. @can('inventory.manage')<a href="{{ route('receipts.create') }}">Catat penerimaan barang</a>.@endcan
            </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="card" id="kartu">
    <div class="card-header d-flex align-items-center gap-2">
      <h5 class="mb-0 me-auto">Kartu stok</h5>
      @if ($batchFilter)
        <span class="small">Batch <span class="nx-num fw-semibold">{{ $batches->firstWhere('id', $batchFilter)?->batch_number ?? '#'.$batchFilter }}</span> · <a href="{{ route('stock.show', $drug) }}#kartu">semua batch</a></span>
      @endif
    </div>
    <div class="table-responsive">
      <table class="table mb-0 nx-table-compact">
        <thead><tr><th>Waktu</th><th>Jenis</th><th>Batch</th><th class="text-end">Masuk</th><th class="text-end">Keluar</th><th class="text-end">Saldo batch</th><th>Keterangan</th><th>Petugas</th></tr></thead>
        <tbody>
          @forelse ($movements as $m)
            <tr>
              <td class="nx-num small text-nowrap">{{ $m->created_at->format('d/m/Y H:i') }}</td>
              <td><span class="badge {{ $m->type->badge() }}">{{ $m->type->label() }}</span></td>
              <td class="nx-num small">{{ $m->batch->batch_number }}</td>
              <td class="text-end nx-num text-success">{{ $m->quantity > 0 ? '+'.number_format($m->quantity, 0, ',', '.') : '' }}</td>
              <td class="text-end nx-num text-danger">{{ $m->quantity < 0 ? '−'.number_format(abs($m->quantity), 0, ',', '.') : '' }}</td>
              <td class="text-end nx-num">{{ number_format($m->balance_after, 0, ',', '.') }}</td>
              <td class="small">
                @if ($m->reference instanceof \App\Modules\Inventory\Models\GoodsReceipt)
                  <a href="{{ route('receipts.show', $m->reference) }}">{{ $m->reference->number }}</a>
                  @if ($m->reference->invoice_number)<span class="text-muted">· faktur {{ $m->reference->invoice_number }}</span>@endif
                @else
                  {{ $m->reason ?? '—' }}
                @endif
              </td>
              <td class="small">{{ $m->user?->name ?? '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada mutasi.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-footer small text-muted d-flex flex-wrap gap-2 align-items-center">
      <span class="me-auto"><i class="bi bi-lock"></i> Satuan: {{ $drug->base_unit }}. Mutasi tidak bisa diubah atau dihapus; koreksi dibuat sebagai penyesuaian baru.</span>
      {{ $movements->fragment('kartu')->links() }}
    </div>
  </div>

  @can('inventory.manage')
    <div class="modal fade" id="adjustModal" tabindex="-1" aria-labelledby="adjustTitle" aria-hidden="true">
      <div class="modal-dialog">
        <form method="POST" class="modal-content" id="adjustForm" action="{{ str_starts_with((string) old('_adjust_url'), url('/stok/batch/')) ? old('_adjust_url') : '' }}">
          @csrf
          <input type="hidden" name="_adjust_url" id="adjustUrl" value="{{ str_starts_with((string) old('_adjust_url'), url('/stok/batch/')) ? old('_adjust_url') : '' }}">
          <div class="modal-header">
            <h5 class="modal-title" id="adjustTitle">Penyesuaian stok · batch <span class="nx-num" id="adjustBatch">{{ old('_adjust_batch') }}</span></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="_adjust_batch" id="adjustBatchInput" value="{{ old('_adjust_batch') }}">
            <p class="small text-muted">Saldo sistem: <span class="nx-num fw-semibold" id="adjustQty"></span> {{ $drug->base_unit }}. Untuk hitung fisik banyak batch sekaligus, pakai <a href="{{ route('stock.opname') }}">stok opname</a>.</p>
            <div class="btn-group w-100 mb-3" role="group" aria-label="Arah penyesuaian">
              <input type="radio" class="btn-check" name="direction" id="dirOut" value="out" @checked(old('direction', 'out') === 'out')>
              <label class="btn btn-outline-danger" for="dirOut"><i class="bi bi-dash-circle me-1"></i>Kurangi</label>
              <input type="radio" class="btn-check" name="direction" id="dirIn" value="in" @checked(old('direction') === 'in')>
              <label class="btn btn-outline-success" for="dirIn"><i class="bi bi-plus-circle me-1"></i>Tambah</label>
            </div>
            <div class="row g-2 mb-3">
              <div class="col-5">
                <label class="form-label" for="adjQty">Jumlah</label>
                <input class="form-control nx-num @error('quantity') is-invalid @enderror" type="number" min="1" id="adjQty" name="quantity" value="{{ old('quantity') }}" required>
                @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-7">
                <label class="form-label" for="adjPack">Kemasan</label>
                <select class="form-select" id="adjPack" name="drug_packaging_id">
                  @foreach ($drug->packagings as $p)
                    <option value="{{ $p->id }}" @selected((int) old('drug_packaging_id', $drug->packagings->first()->id) === $p->id)>{{ $p->unit_name }}{{ $p->quantity_in_base > 1 ? ' ('.$p->quantity_in_base.' '.$drug->base_unit.')' : '' }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="adjReason">Alasan</label>
              <select class="form-select @error('reason_code') is-invalid @enderror" id="adjReason" name="reason_code" required>
                <option value="">Pilih alasan</option>
                @foreach ($reasons as $code => $label)
                  <option value="{{ $code }}" @selected(old('reason_code') === $code)>{{ $label }}</option>
                @endforeach
              </select>
              @error('reason_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div>
              <label class="form-label" for="adjNote">Keterangan</label>
              <textarea class="form-control @error('note') is-invalid @enderror" id="adjNote" name="note" rows="2" required placeholder="mis. 1 botol pecah saat penataan rak">{{ old('note') }}</textarea>
              @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
              <div class="form-text">Tercatat permanen di kartu stok dan audit log atas nama Anda.</div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-soft-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan penyesuaian</button>
          </div>
        </form>
      </div>
    </div>
  @endcan
@endsection

@push('scripts')
<script>
jQuery(function ($) {
  $('.js-adjust').on('click', function () {
    var $b = $(this);
    $('#adjustForm').attr('action', $b.data('url'));
    $('#adjustUrl').val($b.data('url'));
    $('#adjustBatch').text($b.data('batch'));
    $('#adjustBatchInput').val($b.data('batch'));
    $('#adjustQty').text($b.data('qty'));
  });
  @if ($errors->any() && str_starts_with((string) old('_adjust_url'), url('/stok/batch/')))
    new bootstrap.Modal('#adjustModal').show();
  @endif
});
</script>
@endpush
