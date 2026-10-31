@extends('layouts.app', ['title' => $drug->exists ? 'Ubah Obat' : 'Tambah Obat'])

@use('App\Modules\Inventory\Models\Drug')
@use('App\Modules\Inventory\Models\Substance')

@php
  $substanceRows = old('substances', $drug->exists
      ? $drug->substances->map(fn ($s) => ['substance_id' => $s->id, 'strength_value' => $s->pivot->strength_value !== null ? rtrim(rtrim((string) $s->pivot->strength_value, '0'), '.') : null, 'strength_unit' => $s->pivot->strength_unit])->all()
      : [['substance_id' => null, 'strength_value' => null, 'strength_unit' => 'mg']]);
  $substanceNames = Substance::whereIn('id', collect($substanceRows)->pluck('substance_id')->filter())->pluck('name', 'id');

  $packagingRows = old('packagings', $drug->exists
      ? $drug->packagings->map(fn ($p) => $p->only(['id', 'unit_name', 'quantity_in_base', 'barcode']))->all()
      : [['unit_name' => 'Tablet', 'quantity_in_base' => 1], ['unit_name' => 'Strip', 'quantity_in_base' => 10], ['unit_name' => 'Box', 'quantity_in_base' => 100]]);
  $defaultPackaging = old('default_packaging', $drug->exists
      ? $drug->packagings->search(fn ($p) => $p->is_default_sale)
      : 1);
@endphp

<x-tom-select-assets />

@section('content')
  <x-page-head :title="$drug->exists ? $drug->name : 'Tambah Obat'" :crumbs="['Obat & Stok', 'Master Obat' => route('drugs.index')]" />

  @if ($errors->any())
    <div class="alert alert-danger" role="alert">
      <div class="fw-semibold mb-1"><i class="bi bi-x-octagon-fill me-1"></i>Data belum bisa disimpan:</div>
      <ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ $drug->exists ? route('drugs.update', $drug) : route('drugs.store') }}" id="drugForm" novalidate>
    @csrf
    @if ($drug->exists) @method('PUT') @endif

    <div class="row g-4">
      {{-- ===== Identitas ===== --}}
      <div class="col-xl-7">
        <div class="card h-100">
          <div class="card-header"><h5 class="mb-0">Identitas obat</h5></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-8">
                <label class="form-label" for="name">Nama obat (dagang/generik + kekuatan)</label>
                <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $drug->name) }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-4">
                <label class="form-label" for="kfa_code">Kode KFA</label>
                <input class="form-control nx-num @error('kfa_code') is-invalid @enderror" id="kfa_code" name="kfa_code" value="{{ old('kfa_code', $drug->kfa_code) }}">
                @error('kfa_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="col-md-4">
                <label class="form-label" for="dosage_form">Bentuk sediaan</label>
                <select class="form-select" id="dosage_form" name="dosage_form">
                  @foreach (Drug::DOSAGE_FORMS as $form)
                    <option value="{{ $form }}" @selected(old('dosage_form', $drug->dosage_form) === $form)>{{ ucfirst($form) }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="strength">Kekuatan</label>
                <input class="form-control" id="strength" name="strength" value="{{ old('strength', $drug->strength) }}" placeholder="mis. 500 mg">
              </div>
              <div class="col-md-4">
                <label class="form-label" for="category">Golongan</label>
                <select class="form-select @error('category') is-invalid @enderror" id="category" name="category">
                  @foreach ($categories as $value => $label)
                    <option value="{{ $value }}" @selected(old('category', $drug->category?->value) === $value)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="base_unit">Satuan terkecil</label>
                <select class="form-select" id="base_unit" name="base_unit">
                  @foreach (Drug::BASE_UNITS as $unit)
                    <option value="{{ $unit }}" @selected(old('base_unit', $drug->base_unit) === $unit)>{{ $unit }}</option>
                  @endforeach
                </select>
                <div class="form-text">Stok disimpan dalam satuan ini.</div>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="min_stock">Stok minimum</label>
                <div class="input-group">
                  <input class="form-control nx-num @error('min_stock') is-invalid @enderror" type="number" min="0" id="min_stock" name="min_stock" value="{{ old('min_stock', $drug->min_stock) }}">
                  <span class="input-group-text js-base-unit">{{ old('base_unit', $drug->base_unit) }}</span>
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="barcode">Barcode (satuan terkecil)</label>
                <input class="form-control nx-num @error('barcode') is-invalid @enderror" id="barcode" name="barcode" value="{{ old('barcode', $drug->barcode) }}">
              </div>
              <div class="col-12"><x-active-switch :checked="$drug->is_active" label="Obat aktif (muncul di pencarian resep & kasir)" /></div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Komposisi ===== --}}
      <div class="col-xl-5">
        <div class="card h-100">
          <div class="card-header d-flex align-items-center">
            <h5 class="mb-0 me-auto">Komposisi zat aktif</h5>
            <button type="button" class="btn btn-sm btn-soft-primary" data-add-row="#substanceRows" data-template="#substanceTpl"><i class="bi bi-plus-lg"></i> Zat aktif</button>
          </div>
          <div class="card-body">
            <p class="small text-muted">Alergi dan interaksi dideteksi berdasarkan zat aktif, bukan nama dagang.</p>
            <div id="substanceRows" class="d-grid gap-2">
              @foreach ($substanceRows as $key => $row)
                <div class="row g-2 align-items-start js-row">
                  <div class="col-6">
                    <select name="substances[{{ $key }}][substance_id]" data-remote-select data-url="{{ route('substances.search') }}" data-secondary-field="atc_code" aria-label="Zat aktif">
                      @if ($row['substance_id'])<option value="{{ $row['substance_id'] }}" selected>{{ $substanceNames[$row['substance_id']] ?? '#'.$row['substance_id'] }}</option>@endif
                    </select>
                  </div>
                  <div class="col-3"><input class="form-control nx-num" name="substances[{{ $key }}][strength_value]" value="{{ $row['strength_value'] ?? '' }}" inputmode="decimal" placeholder="500" aria-label="Kekuatan"></div>
                  <div class="col-2"><input class="form-control" name="substances[{{ $key }}][strength_unit]" value="{{ $row['strength_unit'] ?? '' }}" placeholder="mg" aria-label="Satuan"></div>
                  <div class="col-1"><button type="button" class="nx-icon-btn js-remove-row" aria-label="Hapus zat aktif"><i class="bi bi-trash"></i></button></div>
                </div>
              @endforeach
            </div>
            <template id="substanceTpl">
              <div class="row g-2 align-items-start js-row">
                <div class="col-6">
                  <select name="substances[__KEY__][substance_id]" data-remote-select data-url="{{ route('substances.search') }}" data-secondary-field="atc_code" aria-label="Zat aktif"></select>
                </div>
                <div class="col-3"><input class="form-control nx-num" name="substances[__KEY__][strength_value]" inputmode="decimal" placeholder="500" aria-label="Kekuatan"></div>
                <div class="col-2"><input class="form-control" name="substances[__KEY__][strength_unit]" value="mg" aria-label="Satuan"></div>
                <div class="col-1"><button type="button" class="nx-icon-btn js-remove-row" aria-label="Hapus zat aktif"><i class="bi bi-trash"></i></button></div>
              </div>
            </template>
            <div class="small mt-3"><a href="{{ route('substances.index') }}" target="_blank" class="nx-link">Zat aktif belum ada? Tambahkan di sini <i class="bi bi-box-arrow-up-right"></i></a></div>
          </div>
        </div>
      </div>

      {{-- ===== Kemasan ===== --}}
      <div class="col-xl-7">
        <div class="card h-100">
          <div class="card-header d-flex align-items-center">
            <h5 class="mb-0 me-auto">Kemasan & konversi</h5>
            <button type="button" class="btn btn-sm btn-soft-primary" data-add-row="#packagingRows" data-template="#packagingTpl"><i class="bi bi-plus-lg"></i> Kemasan</button>
          </div>
          <div class="card-body">
            <div class="row g-2 small text-muted mb-1 d-none d-md-flex">
              <div class="col-md-4">Nama kemasan</div><div class="col-md-3">Isi (satuan terkecil)</div><div class="col-md-3">Barcode</div><div class="col-md-2">Jual default</div>
            </div>
            <div id="packagingRows" class="d-grid gap-2">
              @foreach ($packagingRows as $key => $row)
                <div class="row g-2 align-items-center js-row">
                  <input type="hidden" name="packagings[{{ $key }}][id]" value="{{ $row['id'] ?? '' }}">
                  <div class="col-md-4"><input class="form-control js-pack-name" name="packagings[{{ $key }}][unit_name]" value="{{ $row['unit_name'] ?? '' }}" placeholder="Strip" aria-label="Nama kemasan"></div>
                  <div class="col-md-3"><input class="form-control nx-num js-pack-qty" type="number" min="1" name="packagings[{{ $key }}][quantity_in_base]" value="{{ $row['quantity_in_base'] ?? '' }}" aria-label="Isi dalam satuan terkecil"></div>
                  <div class="col-md-3"><input class="form-control nx-num" name="packagings[{{ $key }}][barcode]" value="{{ $row['barcode'] ?? '' }}" aria-label="Barcode kemasan"></div>
                  <div class="col-md-2 d-flex align-items-center gap-2">
                    <input class="form-check-input mt-0" type="radio" name="default_packaging" value="{{ $key }}" @checked((string) $defaultPackaging === (string) $key) aria-label="Kemasan jual default">
                    <button type="button" class="nx-icon-btn js-remove-row ms-auto" aria-label="Hapus kemasan"><i class="bi bi-trash"></i></button>
                  </div>
                </div>
              @endforeach
            </div>
            <template id="packagingTpl">
              <div class="row g-2 align-items-center js-row">
                <input type="hidden" name="packagings[__KEY__][id]" value="">
                <div class="col-md-4"><input class="form-control js-pack-name" name="packagings[__KEY__][unit_name]" placeholder="Box" aria-label="Nama kemasan"></div>
                <div class="col-md-3"><input class="form-control nx-num js-pack-qty" type="number" min="1" name="packagings[__KEY__][quantity_in_base]" aria-label="Isi dalam satuan terkecil"></div>
                <div class="col-md-3"><input class="form-control nx-num" name="packagings[__KEY__][barcode]" aria-label="Barcode kemasan"></div>
                <div class="col-md-2 d-flex align-items-center gap-2">
                  <input class="form-check-input mt-0" type="radio" name="default_packaging" value="__KEY__" aria-label="Kemasan jual default">
                  <button type="button" class="nx-icon-btn js-remove-row ms-auto" aria-label="Hapus kemasan"><i class="bi bi-trash"></i></button>
                </div>
              </div>
            </template>
            <div class="nx-clinical-alert minor mt-3" aria-live="polite">
              <i class="bi bi-arrow-left-right"></i>
              <div><span class="nx-ca-label d-block">Konversi</span><span id="packPreview">—</span></div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Harga ===== --}}
      <div class="col-xl-5">
        <div class="card h-100">
          <div class="card-header"><h5 class="mb-0">Harga jual per <span class="js-base-unit">{{ old('base_unit', $drug->base_unit) }}</span></h5></div>
          <div class="card-body">
            @foreach ($paymentTypes as $type)
              <div class="row g-2 align-items-center mb-2">
                <label class="col-sm-5 col-form-label" for="price_{{ $type->id }}">
                  {{ $type->name }}
                  @unless ($type->patient_pays)<span class="badge badge-soft-info ms-1">Rp0 ke pasien</span>@endunless
                </label>
                <div class="col-sm-7">
                  <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input class="form-control nx-num text-end js-price @error('prices.'.$type->id) is-invalid @enderror" type="number" min="0" step="1"
                           id="price_{{ $type->id }}" name="prices[{{ $type->id }}]" value="{{ old('prices.'.$type->id, $drug->exists ? $drug->priceFor($type) : null) }}">
                  </div>
                  <div class="form-text js-price-pack"></div>
                </div>
              </div>
            @endforeach
            <div class="form-text">Untuk BPJS/Gratis harga tetap diisi sebagai nilai pemakaian obat di laporan.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2 mt-4">
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>Simpan obat</button>
      <a class="btn btn-soft-secondary" href="{{ route('drugs.index') }}">Batal</a>
    </div>
  </form>
@endsection

@push('scripts')
<script>
jQuery(function ($) {
  var $form = $('#drugForm');

  // Tambah/hapus baris repeater.
  $form.on('click', '[data-add-row]', function () {
    var key = 'n' + Date.now();
    var html = $($(this).data('template')).html().replace(/__KEY__/g, key);
    var $row = $(html).appendTo($(this).data('add-row'));
    nxInitRemoteSelects($row);
    refresh();
  });
  $form.on('click', '.js-remove-row', function () {
    $(this).closest('.js-row').remove();
    refresh();
  });

  function packs() {
    return $('#packagingRows .js-row').map(function () {
      var name = $.trim($(this).find('.js-pack-name').val());
      var qty = parseInt($(this).find('.js-pack-qty').val(), 10);
      return name && qty > 0 ? { name: name, qty: qty, isDefault: $(this).find('[name="default_packaging"]').is(':checked') } : null;
    }).get().sort(function (a, b) { return b.qty - a.qty; });
  }

  function rupiah(n) { return 'Rp' + Math.round(n).toLocaleString('id-ID'); }

  // Pratinjau rantai konversi & harga per kemasan default.
  function refresh() {
    var unit = $('#base_unit').val();
    $('.js-base-unit').text(unit);
    var list = packs();
    $('#packPreview').text(list.length
      ? list.map(function (p) { return '1 ' + p.name + ' = ' + p.qty.toLocaleString('id-ID') + ' ' + unit; }).join(' · ')
      : 'Isi minimal satu kemasan dengan isi 1 (satuan terkecil).');

    var def = list.filter(function (p) { return p.isDefault; })[0];
    $('.js-price').each(function () {
      var price = parseInt(this.value, 10);
      $(this).closest('.col-sm-7').find('.js-price-pack').text(def && def.qty > 1 && price >= 0
        ? '= ' + rupiah(price * def.qty) + ' per ' + def.name : '');
    });
  }

  $form.on('input change', '.js-pack-name, .js-pack-qty, [name="default_packaging"], .js-price, #base_unit', refresh);
  refresh();
});
</script>
@endpush
