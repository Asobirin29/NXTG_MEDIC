@extends('layouts.app', ['title' => 'Transaksi '.$sale->number])

@section('content')
  <x-page-head :title="'Transaksi '.$sale->number" :subtitle="$sale->title().' · '.$sale->paymentType->name.' · '.$sale->completed_at?->translatedFormat('d F Y H:i')" :crumbs="['Farmasi & Kasir' => route('pos.index'), 'Riwayat Transaksi' => route('pos.sales.index')]">
    <a href="{{ route('pos.sales.receipt', $sale) }}" target="_blank" rel="noopener" class="btn btn-soft-secondary"><i class="bi bi-printer me-1"></i>Struk</a>
    @if ($sale->items->where('kind', 'drug')->isNotEmpty())
      <a href="{{ route('pos.labels', $sale) }}" target="_blank" rel="noopener" class="btn btn-soft-secondary"><i class="bi bi-tags me-1"></i>Etiket</a>
    @endif
    <a href="{{ route('pos.index') }}" class="btn btn-primary"><i class="bi bi-cart3 me-1"></i>Kembali ke kasir</a>
  </x-page-head>

  @if ($sale->status === 'voided')
    <div class="alert alert-danger"><i class="bi bi-x-octagon me-1"></i>Di-void {{ $sale->voided_at->format('d/m/Y H:i') }} oleh {{ $sale->voider?->name }}: {{ $sale->void_reason }}</div>
  @endif

  <div class="row g-4">
    <div class="col-xl-8">
      <div class="card">
        <div class="card-header d-flex align-items-center">
          <h5 class="mb-0 me-auto">Rincian</h5>
          <span class="badge {{ $sale->statusBadge() }}">{{ $sale->statusLabel() }}</span>
        </div>
        <div class="table-responsive">
          <table class="table mb-0 nx-table-compact">
            <thead><tr><th>Item</th><th>Batch dipotong</th><th class="text-end">Harga</th><th class="text-end">Subtotal</th><th class="text-end">HPP</th></tr></thead>
            <tbody>
              @foreach ($sale->items as $item)
                <tr>
                  <td>
                    <div class="fw-semibold" style="color:var(--nx-text)">{{ $item->name }}</div>
                    <div class="small text-muted">
                      {{ $item->quantity }} {{ $item->unit_name ?? '×' }}@if ($item->isDrug()) = {{ $item->quantity_base }} {{ $item->drug?->base_unit }}@endif
                      @if ($item->signa) · {{ $item->signa }}@endif
                      @if ($item->returned_base)<span class="badge badge-soft-warning">diretur {{ $item->returned_base }}</span>@endif
                    </div>
                  </td>
                  <td class="small">
                    @foreach ($item->batches as $b)
                      <div class="text-nowrap"><span class="nx-num">{{ $b->batch->batch_number }}</span> · ED {{ $b->batch->expiry_date->format('m/Y') }} · {{ $b->quantity }}</div>
                    @endforeach
                  </td>
                  <td class="text-end small nx-num">Rp{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                  <td class="text-end"><x-rupiah :value="$item->line_total" /></td>
                  <td class="text-end small nx-num text-muted">{{ $item->isDrug() ? 'Rp'.number_format((float) $item->cost_total, 0, ',', '.') : '—' }}</td>
                </tr>
              @endforeach
            </tbody>
            <tfoot>
              <tr><th colspan="3" class="text-end">Nilai</th><th class="text-end"><x-rupiah :value="$sale->total_value" /></th><th class="text-end small nx-num text-muted">Rp{{ number_format((float) $sale->cost_total, 0, ',', '.') }}</th></tr>
              <tr><th colspan="3" class="text-end">Tagihan pasien</th><th class="text-end"><x-rupiah :value="$sale->amount_due" /></th><th></th></tr>
              @if ($sale->amount_due > 0)
                <tr><td colspan="3" class="text-end small">{{ $sale->payment_method === 'cash' ? 'Tunai' : 'Non-tunai' }} · kembali</td><td class="text-end small nx-num">Rp{{ number_format($sale->paid_amount, 0, ',', '.') }} · Rp{{ number_format($sale->change_amount, 0, ',', '.') }}</td><td></td></tr>
              @endif
            </tfoot>
          </table>
        </div>
        <div class="card-footer small text-muted">
          Kasir {{ $sale->cashier?->name }}
          @if ($sale->encounter) · Kunjungan {{ $sale->encounter->queueLabel() }} ({{ $sale->encounter->poli->name }})@endif
          @if ($sale->prescription) · Resep {{ $sale->prescription->doctor?->name }}@endif
          @if ($sale->external_rx_ref) · Resep luar: {{ $sale->external_rx_ref }}@endif
        </div>
      </div>

      @if ($sale->returns->isNotEmpty())
        <div class="card mt-4">
          <div class="card-header"><h5 class="mb-0">Retur</h5></div>
          <ul class="list-group list-group-flush">
            @foreach ($sale->returns as $r)
              <li class="list-group-item small">
                <div class="d-flex justify-content-between"><span class="nx-num fw-semibold">{{ $r->number }}</span><span>Uang kembali <x-rupiah :value="$r->refund_amount" /></span></div>
                <div>{{ $r->items->map(fn ($i) => $i->saleItem->name.' × '.$i->quantity.' ('.($i->condition === 'sellable' ? 'layak jual' : 'karantina').')')->implode(', ') }}</div>
                <div class="text-muted">{{ $r->reason }} · {{ $r->user?->name }}, disetujui {{ $r->approver?->name }} · {{ $r->created_at->format('d/m/Y H:i') }}</div>
              </li>
            @endforeach
          </ul>
        </div>
      @endif
    </div>

    <div class="col-xl-4">
      @if ($sale->isCompleted())
        @php $hasDrugs = $sale->items->where('kind', 'drug')->isNotEmpty(); @endphp

        @can('pos.return.request')
          @if ($hasDrugs)
            <form method="POST" action="{{ route('pos.sales.return', $sale) }}" class="card mb-4" novalidate>
              @csrf
              <div class="card-header"><h6 class="mb-0"><i class="bi bi-arrow-counterclockwise me-1"></i>Retur obat</h6></div>
              <div class="card-body">
                @error('lines')<div class="alert alert-danger small py-2">{{ $message }}</div>@enderror
                @foreach ($sale->items->where('kind', 'drug') as $item)
                  @foreach ($item->batches as $b)
                    @php $left = $b->quantity - $b->returned; @endphp
                    @continue($left <= 0)
                    <div class="border rounded-2 p-2 mb-2">
                      <div class="small fw-semibold">{{ $item->name }} <span class="text-muted nx-num">· {{ $b->batch->batch_number }}</span></div>
                      <div class="row g-2 align-items-center mt-1">
                        <div class="col-5">
                          <div class="input-group input-group-sm">
                            <input class="form-control nx-num @error('lines.'.$b->id.'.quantity') is-invalid @enderror" type="number" min="0" max="{{ $left }}" name="lines[{{ $b->id }}][quantity]" value="{{ old('lines.'.$b->id.'.quantity') }}" placeholder="0" aria-label="Jumlah retur {{ $item->name }}">
                            <span class="input-group-text">/{{ $left }}</span>
                          </div>
                        </div>
                        <div class="col-7">
                          <select class="form-select form-select-sm" name="lines[{{ $b->id }}][condition]" aria-label="Kondisi">
                            <option value="sellable" @selected(old('lines.'.$b->id.'.condition') === 'sellable')>Layak jual</option>
                            <option value="quarantine" @selected(old('lines.'.$b->id.'.condition') === 'quarantine')>Karantina / rusak</option>
                          </select>
                        </div>
                      </div>
                      @error('lines.'.$b->id.'.quantity')<div class="small text-danger">{{ $message }}</div>@enderror
                    </div>
                  @endforeach
                @endforeach
                <div class="form-text mb-2">Jumlah dalam satuan terkecil. Layak jual kembali ke batch asal; karantina tidak akan pernah dikeluarkan lagi.</div>
                <input class="form-control form-control-sm mb-2 @error('reason') is-invalid @enderror" name="reason" value="{{ old('reason') }}" placeholder="Alasan retur" aria-label="Alasan retur" required>
                <div class="row g-2">
                  <div class="col-7">
                    <select class="form-select form-select-sm @error('approver_id') is-invalid @enderror" name="approver_id" aria-label="Apoteker penyetuju">
                      @foreach ($pharmacists as $ph)<option value="{{ $ph->id }}" @selected($ph->id === auth()->id())>{{ $ph->name }}</option>@endforeach
                    </select>
                  </div>
                  <div class="col-5"><input class="form-control form-control-sm nx-num @error('approver_pin') is-invalid @enderror" type="password" name="approver_pin" inputmode="numeric" maxlength="6" placeholder="PIN" aria-label="PIN apoteker" autocomplete="off"></div>
                </div>
                @error('approver_pin')<div class="small text-danger mt-1">{{ $message }}</div>@enderror
              </div>
              <div class="card-footer"><button class="btn btn-warning w-100" type="submit">Proses retur (persetujuan apoteker)</button></div>
            </form>
          @endif
        @endcan

        @can('pos.void')
          @if ($sale->returns->isEmpty())
            <form method="POST" action="{{ route('pos.sales.void', $sale) }}" class="card border-danger" data-confirm="Void transaksi {{ $sale->number }}? Semua stok kembali ke batch asal." novalidate>
              @csrf
              <div class="card-header"><h6 class="mb-0 text-danger"><i class="bi bi-x-octagon me-1"></i>Void transaksi</h6></div>
              <div class="card-body">
                <textarea class="form-control form-control-sm @error('void_reason') is-invalid @enderror" name="void_reason" rows="2" placeholder="Alasan void (wajib)" aria-label="Alasan void" required>{{ old('void_reason') }}</textarea>
                @error('void_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @if ($restricted && ! $isPharmacist)
                  <div class="small fw-semibold mt-2">Berisi psikotropika/narkotika — wajib PIN apoteker</div>
                  <div class="row g-2 mt-1">
                    <div class="col-7">
                      <select class="form-select form-select-sm" name="approver_id" aria-label="Apoteker">
                        @foreach ($pharmacists as $ph)<option value="{{ $ph->id }}">{{ $ph->name }}</option>@endforeach
                      </select>
                    </div>
                    <div class="col-5"><input class="form-control form-control-sm nx-num @error('approver_pin') is-invalid @enderror" type="password" name="approver_pin" inputmode="numeric" maxlength="6" placeholder="PIN" aria-label="PIN apoteker" autocomplete="off"></div>
                  </div>
                  @error('approver_pin')<div class="small text-danger mt-1">{{ $message }}</div>@enderror
                @endif
                @if ($sale->encounter_id)<div class="form-text">Tagihan kunjungan akan dibuka ulang (status Siap Bayar).</div>@endif
              </div>
              <div class="card-footer"><button class="btn btn-danger w-100" type="submit">Void transaksi</button></div>
            </form>
          @endif
        @endcan
      @endif
    </div>
  </div>
@endsection
