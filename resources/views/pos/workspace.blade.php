@extends('layouts.app', ['title' => $sale ? 'Kasir · '.$sale->title() : 'Farmasi & Kasir'])

<x-tom-select-assets />

@section('content')
  <x-page-head title="Farmasi & Kasir" subtitle="Antrean resep poli, penjualan bebas, alokasi batch FEFO, etiket, dan pembayaran." :crumbs="['Pelayanan']">
    <a href="{{ route('pos.sales.index') }}" class="btn btn-soft-secondary"><i class="bi bi-receipt me-1"></i>Riwayat transaksi</a>
    <form method="POST" action="{{ route('pos.open-otc') }}">@csrf
      <button class="btn btn-primary" type="submit"><i class="bi bi-cart-plus me-1"></i>Penjualan bebas (OTC)</button>
    </form>
  </x-page-head>

  <div class="nx-emr-grid">
    {{-- ============ KIRI: antrean ============ --}}
    <div>
      <div class="card nx-emr-sticky nx-pos-queue">
        <div class="card-header pb-0">
          <ul class="nav nav-pills nx-pill-tabs nx-pos-tabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#qRx" type="button" role="tab">Resep <span class="badge bg-danger ms-1">{{ $rxQueue->count() }}</span></button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#qBill" type="button" role="tab">Bayar <span class="badge badge-soft-warning ms-1">{{ $billingQueue->count() }}</span></button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#qHeld" type="button" role="tab">Tahan <span class="badge badge-soft-secondary ms-1">{{ $heldCarts->count() }}</span></button>
            </li>
          </ul>
        </div>
        <div class="tab-content">
          @foreach (['qRx' => $rxQueue, 'qBill' => $billingQueue] as $pane => $list)
            <div class="tab-pane fade @if ($loop->first) show active @endif" id="{{ $pane }}" role="tabpanel">
              <ul class="list-group list-group-flush">
                @forelse ($list as $enc)
                  <li class="list-group-item">
                    <form method="POST" action="{{ route('pos.open-encounter', $enc) }}" class="d-flex gap-2 align-items-center">
                      @csrf
                      <span class="nx-queue-no">{{ $enc->queueLabel() }}</span>
                      <span class="flex-grow-1 min-w-0">
                        <span class="d-block fw-semibold text-truncate" style="color:var(--nx-text)">{{ $enc->patient->name }}</span>
                        <span class="small text-muted">{{ $enc->paymentType->name }} ·
                          @if ($enc->prescription && $enc->prescription->status->value === 'pending_verification')
                            resep {{ $enc->prescription->sent_at?->format('H:i') }}
                          @else
                            {{ $enc->prescription ? 'resep siap' : 'tindakan saja' }}
                          @endif
                        </span>
                      </span>
                      <button class="btn btn-sm btn-soft-primary" type="submit" aria-label="Buka tagihan {{ $enc->patient->name }}">Buka</button>
                    </form>
                  </li>
                @empty
                  <li class="list-group-item small text-muted">{{ $pane === 'qRx' ? 'Belum ada resep masuk.' : 'Tidak ada pasien menunggu pembayaran.' }}</li>
                @endforelse
              </ul>
            </div>
          @endforeach
          <div class="tab-pane fade" id="qHeld" role="tabpanel">
            <ul class="list-group list-group-flush">
              @forelse ($heldCarts as $held)
                <a href="{{ route('pos.cart', $held) }}" @class(['list-group-item list-group-item-action d-flex gap-2', 'active-cart' => $sale && $sale->id === $held->id])>
                  <i class="bi {{ $held->isEncounter() ? 'bi-clipboard2-pulse' : 'bi-bag' }} mt-1"></i>
                  <span class="flex-grow-1 min-w-0">
                    <span class="d-block fw-semibold text-truncate">{{ $held->title() }}</span>
                    <span class="small text-muted">{{ $held->items_count }} item · {{ $held->updated_at->format('H:i') }}</span>
                  </span>
                </a>
              @empty
                <li class="list-group-item small text-muted">Tidak ada keranjang yang ditahan.</li>
              @endforelse
            </ul>
          </div>
        </div>
      </div>
    </div>

    {{-- ============ TENGAH: keranjang ============ --}}
    <div>
      @if (! $sale)
        <div class="card">
          <div class="card-body text-center py-5">
            <span class="nx-icon-sq primary mx-auto mb-3"><i class="bi bi-cart3"></i></span>
            <h5>Belum ada keranjang aktif</h5>
            <p class="text-muted mb-0">Pilih pasien dari antrean resep, lanjutkan keranjang yang ditahan, atau mulai penjualan bebas (OTC).</p>
          </div>
        </div>
      @else
        @php $lines = $quote['lines']; @endphp
        <div class="card">
          <div class="card-header d-flex flex-wrap gap-2 align-items-center">
            <div class="me-auto">
              <h5 class="mb-0">{{ $sale->title() }}</h5>
              <div class="small text-muted">
                {{ $sale->isEncounter() ? 'Tagihan kunjungan '.$sale->encounter->queueLabel().' · '.$sale->encounter->poli->name : 'Penjualan bebas (OTC)' }}
                @if ($sale->prescription) · resep {{ $sale->prescription->doctor?->name }}
                  <span class="badge {{ $sale->prescription->status->badge() }}">{{ $sale->prescription->status->label() }}</span>
                @endif
              </div>
            </div>
            @if ($sale->items->where('kind', 'drug')->isNotEmpty())
              <a class="btn btn-sm btn-soft-secondary" href="{{ route('pos.labels', $sale) }}" target="_blank" rel="noopener"><i class="bi bi-tags me-1"></i>Cetak etiket</a>
            @endif
            <a class="btn btn-sm btn-soft-secondary" href="{{ route('pos.index') }}" title="Keranjang tetap tersimpan, bisa dilanjutkan dari tab Ditahan"><i class="bi bi-pause-circle me-1"></i>Tahan</a>
            <form method="POST" action="{{ route('pos.cart.discard', $sale) }}" data-confirm="Batalkan keranjang ini? Stok tidak berubah.">
              @csrf @method('DELETE')
              <button class="btn btn-sm btn-soft-danger" type="submit"><i class="bi bi-trash me-1"></i>Batalkan</button>
            </form>
          </div>

          {{-- Tambah obat: cari atau scan barcode --}}
          <div class="card-body border-bottom">
            <form method="POST" action="{{ route('pos.cart.items.store', $sale) }}" class="row g-2 align-items-end" id="addForm">
              @csrf
              <div class="col-12">
                <label class="form-label small mb-1" for="drugPicker">Tambah obat</label>
                <select id="drugPicker" name="drug_id" data-url="{{ route('drugs.search') }}" placeholder="Nama obat, zat aktif, atau barcode" required></select>
              </div>
              <div class="col-5">
                <label class="form-label small mb-1" for="addPack">Kemasan</label>
                <select class="form-select" id="addPack" name="drug_packaging_id" required></select>
              </div>
              <div class="col-3">
                <label class="form-label small mb-1" for="addQty">Jumlah</label>
                <input class="form-control nx-num" type="number" min="1" id="addQty" name="quantity" value="1" required>
              </div>
              <div class="col-4"><button class="btn btn-primary w-100 text-nowrap" type="submit"><i class="bi bi-plus-lg"></i> Tambah</button></div>
              <div class="col-12"><input class="form-control form-control-sm" name="signa" maxlength="120" placeholder="Aturan pakai (opsional untuk OTC), mis. 3 x 1 tablet sesudah makan" aria-label="Aturan pakai"></div>
              <div class="col-12 small text-muted" id="addInfo"></div>
            </form>
            <form method="POST" action="{{ route('pos.cart.scan', $sale) }}" class="input-group input-group-sm mt-2">
              @csrf
              <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
              <input class="form-control nx-num" name="barcode" placeholder="Scan barcode kemasan lalu Enter" aria-label="Scan barcode" autocomplete="off">
            </form>
          </div>

          <div class="table-responsive">
            <table class="table mb-0 nx-table-compact align-middle">
              <thead><tr><th>Item</th><th>Batch FEFO</th><th class="text-end">Subtotal</th><th></th></tr></thead>
              <tbody>
                @forelse ($sale->items as $item)
                  @php $line = $lines[$item->id]; @endphp
                  <tr>
                    <td>
                      <div class="fw-semibold" style="color:var(--nx-text)">
                        {{ $item->name }}
                        @if ($item->isFromPrescription())<span class="badge badge-soft-primary">Resep</span>@endif
                        @if ($item->drug)<span class="badge {{ $item->drug->category->badge() }}">{{ $item->drug->category->label() }}</span>@endif
                        @if ($item->kind === 'service')<span class="badge badge-soft-info">Tindakan</span>@endif
                      </div>
                      <div class="small">
                        @if ($item->isDrug())
                          <span class="nx-num fw-semibold">{{ $item->quantity }} {{ $item->unit_name }}</span>
                          <span class="text-muted">= {{ number_format($item->quantity_base, 0, ',', '.') }} {{ $item->drug->base_unit }}</span>
                          @if ($item->signa)<div class="text-muted"><i class="bi bi-chat-square-text"></i> {{ $item->signa }}</div>@endif
                        @else
                          <span class="text-muted">{{ $item->quantity }} ×</span>
                        @endif
                      </div>
                    </td>
                    <td class="small">
                      @if ($line['shortage'])
                        <span class="text-danger"><i class="bi bi-exclamation-triangle"></i> Stok kurang</span>
                      @else
                        @foreach ($line['allocation'] as $a)
                          <div class="text-nowrap"><span class="nx-num">{{ $a['batch_number'] }}</span> · ED {{ $a['expiry_date']->format('m/Y') }} · <span class="nx-num">{{ $a['quantity'] }}</span></div>
                        @endforeach
                      @endif
                    </td>
                    
                    <td class="text-end text-nowrap"><x-rupiah :value="$line['line_total']" />
                      @if ($line['unit_price'] === null)<div class="small text-danger">harga belum diatur</div>@else<div class="small text-muted nx-num">Rp{{ number_format($line['unit_price'], 0, ',', '.') }}/{{ $item->isDrug() ? $item->drug->base_unit : 'tindakan' }}</div>@endif
                    </td>
                    <td class="text-end">
                      @if ($item->isDrug() && (! $item->isFromPrescription() || $isPharmacist))
                        <form method="POST" action="{{ route('pos.cart.items.destroy', [$sale, $item]) }}" @if ($item->isFromPrescription()) data-confirm="Keluarkan obat resep ini dari keranjang?" @endif>
                          @csrf @method('DELETE')
                          <button class="btn btn-sm btn-soft-danger" type="submit" aria-label="Keluarkan {{ $item->name }}"><i class="bi bi-x-lg"></i></button>
                        </form>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="4" class="text-center text-muted py-4">Keranjang kosong. Cari obat atau scan barcode.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
          <div class="card-footer small text-muted"><i class="bi bi-info-circle"></i> Batch di atas adalah rencana FEFO saat ini; batch final dikunci ulang saat Bayar.</div>
        </div>
      @endif
    </div>

    {{-- ============ KANAN: pasien, CDS, pembayaran ============ --}}
    @if ($sale)
      <div class="nx-emr-right">
        <div class="nx-emr-sticky">
          {{-- Pasien --}}
          <div class="card">
            <div class="card-body">
              @if ($sale->patient)
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <div>
                    <div class="fw-semibold" style="color:var(--nx-text)">{{ $sale->patient->name }}</div>
                    <div class="small text-muted">{{ $sale->patient->medical_record_number }} · {{ $sale->patient->ageLabel() }}</div>
                  </div>
                  @if ($sale->patient->isChild() && $sale->encounter?->latestVital?->weight)
                    <span class="badge badge-soft-warning fs-6" title="Berat badan anak (acuan dosis)">BB {{ rtrim(rtrim((string) $sale->encounter->latestVital->weight, '0'), '.') }} kg</span>
                  @endif
                </div>
                <x-allergy-banner :allergies="$sale->patient->allergies" />
              @else
                <div class="small text-muted mb-1"><i class="bi bi-person"></i> Pembeli tanpa data pasien — alergi tidak bisa dicek.</div>
              @endif

              <form method="POST" action="{{ route('pos.cart.update', $sale) }}" class="mt-3">
                @csrf @method('PUT')
                @unless ($sale->isEncounter())
                  <label class="form-label small mb-1" for="patientPicker">Pasien (untuk cek alergi)</label>
                  <select id="patientPicker" name="patient_id" data-url="{{ route('pos.patients') }}" placeholder="Cari nama / No. RM">
                    <option value="">—</option>
                    @if ($sale->patient)<option value="{{ $sale->patient->id }}" selected>{{ $sale->patient->name }}</option>@endif
                  </select>
                  <div class="row g-2 mt-1">
                    <div class="col-6">
                      <label class="form-label small mb-1" for="payType">Jenis bayar</label>
                      <select class="form-select form-select-sm" id="payType" name="payment_type_id">
                        @foreach ($paymentTypes as $pt)<option value="{{ $pt->id }}" @selected($pt->id === $sale->payment_type_id)>{{ $pt->name }}</option>@endforeach
                      </select>
                    </div>
                    <div class="col-6">
                      <label class="form-label small mb-1" for="cartLabel">Penanda</label>
                      <input class="form-control form-control-sm" id="cartLabel" name="label" value="{{ $sale->label }}" maxlength="60" placeholder="mis. Ibu baju merah">
                    </div>
                  </div>
                @else
                  <div class="small">Jenis bayar: <span class="fw-semibold">{{ $sale->paymentType->name }}</span> <span class="text-muted">(sesuai pendaftaran)</span></div>
                @endunless
                @if ($quote['needs_rx_ref'] || $sale->external_rx_ref)
                  <label class="form-label small mb-1 mt-2" for="rxRef">Rujukan resep luar <span class="text-danger">*</span></label>
                  <input class="form-control form-control-sm @if ($quote['needs_rx_ref'] && blank($sale->external_rx_ref)) is-invalid @endif" id="rxRef" name="external_rx_ref" value="{{ $sale->external_rx_ref }}" maxlength="150" placeholder="dr. … · No./tgl resep">
                  <div class="form-text">Wajib untuk obat keras, psikotropika, dan narkotika di luar resep poli.</div>
                @endif
                <button class="btn btn-sm btn-soft-secondary mt-2" type="submit" data-allow-resubmit>Simpan detail</button>
              </form>
            </div>
          </div>

          {{-- Peringatan klinis --}}
          <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-shield-exclamation me-1"></i>Cek alergi & interaksi</h6></div>
            <div class="card-body">
              @php $pendingKeys = collect($quote['pending_major'])->pluck('key')->all(); @endphp
              @if (empty($quote['alerts']))
                <div class="nx-clinical-alert minor"><i class="bi bi-shield-check"></i><span>Tidak ada peringatan alergi, interaksi, atau duplikasi.</span></div>
              @endif
              @foreach ($quote['alerts'] as $alert)
                <div class="nx-clinical-alert {{ $alert->severity->value }} mb-2" role="{{ $alert->requiresOverride() ? 'alert' : 'note' }}">
                  <i class="bi {{ $alert->severity->icon() }}"></i>
                  <div class="flex-grow-1 min-w-0">
                    <span class="nx-ca-label d-block">{{ $alert->severity->label() }} · {{ $alert->typeLabel() }}</span>
                    <div class="fw-semibold">{{ $alert->title }}</div>
                    <div class="small">{{ $alert->message }}</div>
                    @if ($ov = $doctorOverrides->get($alert->key))
                      <div class="small mt-1 p-2 rounded-2" style="background:var(--nx-card-bg)"><span class="fw-semibold">Alasan dokter:</span> {{ $ov->reason }} <span class="text-muted">— {{ $ov->user?->name }}</span></div>
                    @endif
                    @if (in_array($alert->key, $quote['approved_keys'], true))
                      <div class="small mt-1 text-success"><i class="bi bi-check2-circle"></i> Sudah disetujui apoteker</div>
                    @elseif (in_array($alert->key, $pendingKeys, true))
                      <label class="form-label small fw-semibold mt-2 mb-1" for="ov_{{ md5($alert->key) }}">Alasan tetap diserahkan</label>
                      <textarea class="form-control form-control-sm @error('overrides.'.$alert->key) is-invalid @enderror" rows="2" id="ov_{{ md5($alert->key) }}"
                                name="overrides[{{ $alert->key }}]" form="{{ $quote['awaiting_verification'] ? 'verifyForm' : 'payForm' }}" placeholder="Wajib diisi, atau keluarkan obatnya">{{ old('overrides.'.$alert->key) }}</textarea>
                      @error('overrides.'.$alert->key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @endif
                  </div>
                </div>
              @endforeach
            </div>
          </div>

          {{-- Verifikasi resep (apoteker) --}}
          @if ($quote['awaiting_verification'])
            <div class="card border-warning">
              <div class="card-body">
                <h6><i class="bi bi-clipboard-check me-1"></i>Verifikasi resep</h6>
                @if ($isPharmacist)
                  <p class="small text-muted">Telaah dosis, jumlah, dan aturan pakai. Setelah diverifikasi, resep berstatus <em>Disiapkan</em> dan pasien bisa membayar.</p>
                  <form method="POST" action="{{ route('pos.cart.verify', $sale) }}" id="verifyForm">
                    @csrf
                    <button class="btn btn-warning w-100" type="submit"><i class="bi bi-check2-square me-1"></i>Verifikasi & siapkan obat</button>
                  </form>
                @else
                  <p class="small mb-0">Menunggu verifikasi apoteker sebelum pembayaran.</p>
                @endif
              </div>
            </div>
          @endif

          {{-- Pembayaran --}}
          <div class="card">
            <div class="card-body">
              <div class="d-flex justify-content-between small"><span class="text-muted">Nilai obat & tindakan</span><x-rupiah :value="$quote['total_value']" /></div>
              @unless ($sale->paymentType->patient_pays)
                <div class="small text-muted">{{ $sale->paymentType->name }}: tagihan pasien Rp0, nilai tetap tercatat.</div>
              @endunless
              <div class="d-flex justify-content-between align-items-baseline mt-2">
                <span class="fw-semibold">Tagihan pasien</span>
                <span class="fs-3 fw-bold nx-num" id="amountDue" data-value="{{ $quote['amount_due'] }}">Rp{{ number_format($quote['amount_due'], 0, ',', '.') }}</span>
              </div>

              @if ($quote['problems'])
                <ul class="small text-danger ps-3 mt-2 mb-2">@foreach ($quote['problems'] as $p)<li>{{ $p }}</li>@endforeach</ul>
              @endif

              <form method="POST" action="{{ route('pos.cart.checkout', $sale) }}" id="payForm" class="mt-2">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                @if ($quote['amount_due'] > 0)
                  <div class="btn-group w-100 mb-2" role="group" aria-label="Metode bayar">
                    <input type="radio" class="btn-check" name="payment_method" id="pmCash" value="cash" @checked(old('payment_method', 'cash') === 'cash')>
                    <label class="btn btn-outline-primary" for="pmCash"><i class="bi bi-cash me-1"></i>Tunai</label>
                    <input type="radio" class="btn-check" name="payment_method" id="pmNon" value="non_cash" @checked(old('payment_method') === 'non_cash')>
                    <label class="btn btn-outline-primary" for="pmNon"><i class="bi bi-qr-code me-1"></i>Non-tunai</label>
                  </div>
                  <div id="cashBox">
                    <label class="form-label small mb-1" for="paid">Uang diterima</label>
                    <div class="input-group">
                      <span class="input-group-text">Rp</span>
                      <input class="form-control nx-num @error('paid_amount') is-invalid @enderror" type="number" min="0" id="paid" name="paid_amount" value="{{ old('paid_amount') }}">
                    </div>
                    @error('paid_amount')<div class="small text-danger">{{ $message }}</div>@enderror
                    <div class="d-flex gap-1 flex-wrap mt-1">
                      @foreach ([$quote['amount_due'], 20000, 50000, 100000] as $v)
                        @if ($v >= $quote['amount_due'])<button type="button" class="btn btn-sm btn-soft-secondary js-cash" data-v="{{ $v }}">{{ $v === $quote['amount_due'] ? 'Uang pas' : number_format($v / 1000, 0).'rb' }}</button>@endif
                      @endforeach
                    </div>
                    <div class="d-flex justify-content-between small mt-2"><span>Kembalian</span><span class="nx-num fw-semibold" id="change">Rp0</span></div>
                  </div>
                @endif

                @if ($quote['pending_major'] && ! $quote['awaiting_verification'] && ! $isPharmacist)
                  <div class="border rounded-2 p-2 mt-2">
                    <div class="small fw-semibold mb-1"><i class="bi bi-person-check me-1"></i>Persetujuan apoteker (peringatan berat)</div>
                    <div class="row g-2">
                      <div class="col-7">
                        <select class="form-select form-select-sm" name="approver_id" aria-label="Apoteker">
                          @foreach ($pharmacists as $ph)<option value="{{ $ph->id }}">{{ $ph->name }}</option>@endforeach
                        </select>
                      </div>
                      <div class="col-5"><input class="form-control form-control-sm nx-num @error('approver_pin') is-invalid @enderror" type="password" name="approver_pin" inputmode="numeric" maxlength="6" placeholder="PIN" aria-label="PIN apoteker" autocomplete="off"></div>
                    </div>
                    @error('approver_pin')<div class="small text-danger mt-1">{{ $message }}</div>@enderror
                  </div>
                @endif

                <button class="btn btn-primary btn-lg w-100 mt-3" type="submit" @disabled($quote['problems'])>
                  <i class="bi bi-check2-circle me-1"></i>{{ $quote['amount_due'] > 0 ? 'Bayar' : 'Selesaikan (Rp0)' }}
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    @endif
  </div>
@endsection

@if ($sale)
@push('scripts')
<script>
jQuery(function ($) {
  var esc = function (s) { return $('<span>').text(s == null ? '' : s).html(); };

  var picker = new TomSelect('#drugPicker', {
    valueField: 'id', labelField: 'name', searchField: ['name', 'composition'], maxOptions: 20, loadThrottle: 250,
    load: function (q, cb) { $.getJSON($('#drugPicker').data('url'), { q: q }).done(cb).fail(function () { cb(); }); },
    render: {
      option: function (d) {
        return '<div><div class="fw-semibold">' + esc(d.name) + ' <span class="badge ' + d.category_badge + '">' + esc(d.category_label) + '</span></div>' +
          '<div class="small text-muted">' + esc(d.composition) + ' · Stok: ' + esc(d.stock_label) + '</div></div>';
      },
      no_results: function () { return '<div class="no-results">Obat tidak ditemukan.</div>'; }
    },
    onChange: function (id) {
      var d = this.options[id], $pack = $('#addPack').empty();
      if (!d) { $('#addInfo').empty(); return; }
      d.packagings.forEach(function (p) {
        $pack.append($('<option>').val(p.id).text(p.unit_name + (p.quantity_in_base > 1 ? ' (' + p.quantity_in_base + ' ' + d.base_unit + ')' : '')).prop('selected', p.is_default));
      });
      $('#addInfo').html(esc(d.composition) + ' · Stok: <span class="nx-num">' + esc(d.stock_label) + '</span>');
      $('#addQty').trigger('focus').trigger('select');
    }
  });

  if ($('#patientPicker').length) {
    new TomSelect('#patientPicker', {
      valueField: 'id', labelField: 'name', searchField: ['name', 'detail'], loadThrottle: 250, allowEmptyOption: true,
      load: function (q, cb) { $.getJSON($('#patientPicker').data('url'), { q: q }).done(cb).fail(function () { cb(); }); },
      render: { option: function (p) { return '<div>' + esc(p.name) + (p.detail ? ' <span class="small text-muted">' + esc(p.detail) + '</span>' : '') + '</div>'; } }
    });
  }

  // Kembalian & metode bayar
  var due = parseInt($('#amountDue').data('value'), 10) || 0;
  function refreshChange() {
    var paid = parseInt($('#paid').val(), 10) || 0;
    $('#change').text('Rp' + Math.max(0, paid - due).toLocaleString('id-ID')).toggleClass('text-danger', paid > 0 && paid < due);
  }
  function refreshMethod() { $('#cashBox').toggle($('#pmCash').is(':checked')); }
  $('#paid').on('input', refreshChange);
  $('.js-cash').on('click', function () { $('#paid').val($(this).data('v')); refreshChange(); });
  $('input[name=payment_method]').on('change', refreshMethod);
  refreshChange(); refreshMethod();
});
</script>
@endpush
@endif
