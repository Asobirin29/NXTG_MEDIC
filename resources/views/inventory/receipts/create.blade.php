@extends('layouts.app', ['title' => 'Terima Barang'])

<x-tom-select-assets />

@section('content')
  <x-page-head title="Terima Barang" subtitle="Catat obat yang datang per batch. Jumlah kemasan otomatis dikonversi ke satuan terkecil." :crumbs="['Obat & Stok', 'Penerimaan Barang' => route('receipts.index')]" />

  @if ($suppliers->isEmpty())
    <div class="alert alert-warning">Belum ada pemasok aktif. <a href="{{ route('suppliers.index') }}">Tambah pemasok</a> dulu.</div>
  @endif

  @if ($errors->any())
    <div class="alert alert-danger" role="alert">
      <div class="fw-semibold mb-1">Penerimaan belum tersimpan:</div>
      <ul class="mb-0 small">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('receipts.store') }}" id="receiptForm" novalidate>
    @csrf
    <div class="card mb-4">
      <div class="card-body row g-3">
        <div class="col-md-4">
          <label class="form-label" for="supplier_id">Pemasok</label>
          <select class="form-select @error('supplier_id') is-invalid @enderror" id="supplier_id" name="supplier_id" required>
            <option value="">Pilih pemasok</option>
            @foreach ($suppliers as $s)
              <option value="{{ $s->id }}" @selected((int) old('supplier_id') === $s->id)>{{ $s->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="invoice_number">No. faktur <span class="text-muted">(opsional)</span></label>
          <input class="form-control nx-num" id="invoice_number" name="invoice_number" value="{{ old('invoice_number') }}">
        </div>
        <div class="col-md-2">
          <label class="form-label" for="received_at">Tanggal terima</label>
          <input class="form-control nx-num @error('received_at') is-invalid @enderror" type="date" id="received_at" name="received_at" value="{{ old('received_at', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="notes">Catatan <span class="text-muted">(opsional)</span></label>
          <input class="form-control" id="notes" name="notes" value="{{ old('notes') }}">
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header d-flex align-items-center">
        <h5 class="mb-0 me-auto">Obat diterima</h5>
        <span class="small text-muted">Batch bernomor sama yang sudah ada akan ditambah saldonya.</span>
      </div>
      <div class="table-responsive">
        <table class="table mb-0 nx-table-compact align-middle">
          <thead>
            <tr>
              <th style="min-width:16rem">Obat</th><th style="min-width:9rem">Kemasan</th><th style="width:7rem">Jumlah</th>
              <th style="min-width:8rem">No. batch</th><th style="width:10rem">ED</th><th style="min-width:9rem">Harga beli / kemasan</th>
              <th class="text-end" style="min-width:8rem">Subtotal</th><th></th>
            </tr>
          </thead>
          <tbody id="rows"></tbody>
        </table>
      </div>
      <div class="card-footer d-flex flex-wrap gap-3 align-items-center">
        <button type="button" class="btn btn-soft-primary" id="addRow"><i class="bi bi-plus-lg me-1"></i>Tambah baris</button>
        <div class="ms-auto text-end">
          <div class="small text-muted">Total faktur</div>
          <div class="fs-5 fw-semibold nx-num" id="grandTotal">Rp0</div>
        </div>
        <button class="btn btn-primary" type="submit" @disabled($suppliers->isEmpty())><i class="bi bi-box-arrow-in-down me-1"></i>Simpan penerimaan</button>
      </div>
    </div>
  </form>

  <template id="rowTpl">
    <tr>
      <td><select class="js-drug" data-name="drug_id" placeholder="Cari nama obat / zat aktif"></select><div class="small text-muted js-info mt-1"></div></td>
      <td><select class="form-select form-select-sm js-pack" data-name="drug_packaging_id"></select></td>
      <td><input class="form-control form-control-sm nx-num js-qty" type="number" min="1" data-name="quantity"></td>
      <td><input class="form-control form-control-sm nx-num text-uppercase" data-name="batch_number" maxlength="60"></td>
      <td><input class="form-control form-control-sm nx-num" type="date" data-name="expiry_date" min="{{ today()->addDay()->toDateString() }}"></td>
      <td><div class="input-group input-group-sm"><span class="input-group-text">Rp</span><input class="form-control nx-num js-price" type="number" min="0" data-name="unit_price"></div></td>
      <td class="text-end nx-num js-subtotal">Rp0</td>
      <td class="text-end"><button type="button" class="btn btn-sm btn-soft-danger js-remove" aria-label="Hapus baris"><i class="bi bi-x-lg"></i></button></td>
    </tr>
  </template>
@endsection

@push('scripts')
<script>
jQuery(function ($) {
  var esc = function (s) { return $('<span>').text(s == null ? '' : s).html(); };
  var rupiah = function (n) { return 'Rp' + (n || 0).toLocaleString('id-ID'); };
  var searchUrl = @json(route('drugs.search'));
  var oldRows = @json(array_values(old('items', [])));
  var oldDrugs = @json($oldDrugs);
  var errors = @json($errors->getMessages());
  var seq = 0;

  function fillPacks($row, drug, selected) {
    var $pack = $row.find('.js-pack').empty();
    drug.packagings.slice().sort(function (a, b) { return b.quantity_in_base - a.quantity_in_base; }).forEach(function (p) {
      var label = p.unit_name + (p.quantity_in_base > 1 ? ' (' + p.quantity_in_base + ' ' + drug.base_unit + ')' : '');
      $pack.append($('<option>').val(p.id).text(label).attr('data-size', p.quantity_in_base)
        .prop('selected', selected ? String(p.id) === String(selected) : p.quantity_in_base === Math.max.apply(null, drug.packagings.map(function (x) { return x.quantity_in_base; }))));
    });
    $row.find('.js-info').text(drug.composition || '');
  }

  function addRow(data) {
    var i = seq++;
    var $row = $($('#rowTpl').html());
    $row.find('[data-name]').each(function () {
      var field = $(this).data('name');
      $(this).attr('name', 'items[' + i + '][' + field + ']');
      if (data && data[field] != null && field !== 'drug_id' && field !== 'drug_packaging_id') this.value = data[field];
    });
    $('#rows').append($row);

    var ts = new TomSelect($row.find('.js-drug')[0], {
      valueField: 'id', labelField: 'name', searchField: ['name', 'composition'], maxOptions: 20, loadThrottle: 250,
      dropdownParent: 'body', // tabel bisa di-scroll horizontal; dropdown jangan ikut terpotong
      load: function (q, cb) { $.getJSON(searchUrl, { q: q }).done(cb).fail(function () { cb(); }); },
      render: {
        option: function (d) { return '<div><div class="fw-semibold">' + esc(d.name) + '</div><div class="small text-muted">' + esc(d.composition) + (d.stock_label ? ' · Stok: ' + esc(d.stock_label) : '') + '</div></div>'; },
        no_results: function () { return '<div class="no-results">Obat tidak ditemukan.</div>'; }
      },
      onChange: function (id) {
        var d = this.options[id];
        if (d) { fillPacks($row, d); $row.find('.js-qty').trigger('focus'); } else { $row.find('.js-pack').empty(); $row.find('.js-info').empty(); }
        total();
      }
    });

    if (data && data.drug_id && oldDrugs[data.drug_id]) {
      var d = oldDrugs[data.drug_id];
      ts.addOption(d);
      ts.setValue(String(d.id), true);
      fillPacks($row, d, data.drug_packaging_id);
    }
    return $row;
  }

  function total() {
    var sum = 0;
    $('#rows tr').each(function () {
      var sub = (parseInt($(this).find('.js-qty').val(), 10) || 0) * (parseInt($(this).find('.js-price').val(), 10) || 0);
      $(this).find('.js-subtotal').text(rupiah(sub));
      sum += sub;
    });
    $('#grandTotal').text(rupiah(sum));
  }

  $('#addRow').on('click', function () { addRow().find('.js-drug')[0].tomselect.focus(); });
  $('#rows').on('click', '.js-remove', function () {
    if ($('#rows tr').length > 1) { $(this).closest('tr').remove(); total(); }
  });
  $('#rows').on('input change', '.js-qty, .js-price', total);

  if (oldRows.length) { oldRows.forEach(addRow); } else { addRow(); }

  // Tandai kolom yang gagal validasi (indeks baris = urutan saat dikirim).
  $.each(errors, function (key) {
    var m = key.match(/^items\.(\d+)\.(\w+)$/);
    if (!m) return;
    var $field = $('#rows tr').eq(parseInt(m[1], 10)).find('[data-name="' + m[2] + '"]');
    $field.addClass('is-invalid');
    if ($field[0] && $field[0].tomselect) $($field[0].tomselect.wrapper).addClass('is-invalid');
  });
  total();
});
</script>
@endpush
