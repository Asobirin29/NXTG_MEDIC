@extends('layouts.app', ['title' => 'Periksa: '.$patient->name])

@use('App\Modules\Patient\Enums\EncounterStatus')
@use('App\Modules\Emr\Models\ClinicalNote')

@php
  $started = $note !== null;
  $locked = $note?->isLocked() ?? false;
  $editable = $started && ! $locked && $encounter->status === EncounterStatus::InConsultation;
@endphp

<x-tom-select-assets />

@section('content')
  <x-page-head :title="$patient->name" :subtitle="$encounter->queueLabel().' · '.$encounter->poli->name.' · '.$encounter->paymentType->name" :crumbs="['Antrean Poli' => route('emr.index')]">
    <span class="badge {{ $encounter->status->badge() }} fs-6"><i class="bi {{ $encounter->status->icon() }}"></i> {{ $encounter->status->label() }}</span>
  </x-page-head>

  <div class="nx-emr-grid">
    {{-- ================= KIRI: konteks pasien (25%) ================= --}}
    <div class="nx-emr-left">
      <div class="card">
        <div class="card-body">
          <div class="d-flex align-items-center gap-3 mb-3">
            <span class="nx-avatar-initial lg">{{ mb_strtoupper(mb_substr($patient->name, 0, 1)) }}</span>
            <div>
              <div class="small text-muted nx-num">RM {{ $patient->medical_record_number }}</div>
              <div class="fw-semibold" style="color:var(--nx-text)">{{ $patient->genderLabel() }} · {{ $patient->ageLabel() }}</div>
              <div class="small text-muted">Lahir {{ $patient->birth_date->format('d/m/Y') }}</div>
            </div>
          </div>
          <x-allergy-banner :allergies="$patient->allergies" />
        </div>
      </div>

      <div class="card">
        <div class="card-header d-flex align-items-center">
          <h5 class="mb-0 me-auto">Tanda vital hari ini</h5>
          @if ($encounter->status->isOpen() && ! $locked)
            <a href="{{ route('emr.assessment', $encounter) }}" class="btn btn-sm btn-soft-secondary"><i class="bi bi-pencil"></i> Ukur</a>
          @endif
        </div>
        <div class="card-body">
          @include('emr.partials.vitals', ['vital' => $vital, 'patient' => $patient])
          @if ($vital?->chief_complaint)
            <div class="small mt-3"><span class="text-muted fw-semibold d-block">Keluhan awal (perawat)</span><span style="white-space:pre-line">{{ $vital->chief_complaint }}</span></div>
          @endif
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h5 class="mb-0">Riwayat kunjungan</h5></div>
        <div class="card-body">
          @forelse ($history as $h)
            <button type="button" class="nx-history-item" data-bs-toggle="modal" data-bs-target="#history{{ $h->id }}">
              <div class="d-flex justify-content-between small"><span class="nx-num fw-semibold">{{ $h->registered_at->format('d/m/Y') }}</span><span class="text-muted">{{ $h->poli->name }}</span></div>
              <div class="small">
                @php $primary = $h->diagnoses->firstWhere('type', 'primary'); @endphp
                {{ $primary ? $primary->icd10->code.' '.$primary->icd10->name_id : 'Tanpa diagnosis' }}
              </div>
            </button>
          @empty
            <div class="text-muted small">Belum ada riwayat kunjungan sebelumnya.</div>
          @endforelse
        </div>
      </div>
    </div>

    {{-- ================= TENGAH: SOAP & ICD-10 (45%) ================= --}}
    <div class="nx-emr-middle">
      @if (! $started)
        <div class="card">
          <div class="card-body text-center py-5">
            <span class="nx-icon-sq primary mx-auto mb-3" style="width:56px;height:56px;font-size:24px"><i class="bi bi-megaphone"></i></span>
            <h2 class="h4">Panggil pasien</h2>
            <p class="text-muted">{{ $encounter->queueLabel() }} — {{ $patient->name }}. @unless ($vital) Tanda vital belum diukur. @endunless</p>
            @if ($encounter->status->canTransitionTo(EncounterStatus::InConsultation) || $encounter->status === EncounterStatus::InConsultation)
              <form method="POST" action="{{ route('emr.start', $encounter) }}">
                @csrf
                <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-play-fill me-1"></i>Mulai periksa</button>
              </form>
            @else
              <div class="text-muted">Kunjungan berstatus {{ $encounter->status->label() }}.</div>
            @endif
          </div>
        </div>
      @elseif ($locked)
        <div class="card">
          <div class="card-header d-flex align-items-center">
            <h5 class="mb-0 me-auto">Rekam medis</h5>
            <span class="badge badge-soft-secondary"><i class="bi bi-lock-fill"></i> Terkunci</span>
          </div>
          <div class="card-body">@include('emr.partials.record', ['encounter' => $encounter])</div>
          <div class="card-footer">
            <form method="POST" action="{{ route('emr.addendum', $encounter) }}" novalidate>
              @csrf
              <label class="form-label" for="addendum">Tambah addendum (koreksi/tambahan)</label>
              <textarea class="form-control @error('content') is-invalid @enderror" id="addendum" name="content" rows="3" required>{{ old('content') }}</textarea>
              @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
              <div class="form-text mb-2">Catatan asli tidak berubah; addendum tercatat dengan nama dan waktu Anda.</div>
              <button class="btn btn-sm btn-primary" type="submit">Simpan addendum</button>
            </form>
          </div>
        </div>
      @else
        <form method="POST" action="{{ route('emr.soap', $encounter) }}" id="soapForm" data-allow-resubmit novalidate>
          @csrf
          @method('PUT')
          <div class="card">
            <div class="card-header d-flex align-items-center">
              <h5 class="mb-0 me-auto">Pemeriksaan (SOAP)</h5>
              <span class="nx-save-state" id="saveState" aria-live="polite">Draf tersimpan {{ $note->updated_at->format('H:i') }}</span>
            </div>
            <div class="card-body">
              @foreach (ClinicalNote::SECTIONS as $field => $label)
                <div class="mb-3">
                  <div class="nx-soap-label">
                    <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                    @if ($field === 'objective' && $vital)
                      <button type="button" class="btn btn-sm btn-soft-secondary py-0" data-insert="#objective" data-text="{{ $vital->summary() }}"><i class="bi bi-heart-pulse"></i> Sisipkan TTV</button>
                    @endif
                    @if ($field === 'subjective' && $vital?->chief_complaint)
                      <button type="button" class="btn btn-sm btn-soft-secondary py-0" data-insert="#subjective" data-text="{{ $vital->chief_complaint }}"><i class="bi bi-chat-left-text"></i> Keluhan perawat</button>
                    @endif
                    @if ($templates->has($field))
                      <div class="dropdown ms-auto">
                        <button class="btn btn-sm btn-soft-secondary py-0 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-lightning-charge"></i> Template</button>
                        <ul class="dropdown-menu dropdown-menu-end">
                          @foreach ($templates[$field] as $t)
                            <li><button type="button" class="dropdown-item" data-insert="#{{ $field }}" data-text="{{ $t->body }}">{{ $t->title }}</button></li>
                          @endforeach
                        </ul>
                      </div>
                    @endif
                  </div>

                  @if ($field === 'assessment')
                    {{-- Diagnosis ICD-10 --}}
                    <div class="border rounded-3 p-2 mb-2" style="border-color:var(--nx-border)!important">
                      <div class="row g-2 align-items-center">
                        <div class="col-md-7">
                          <select id="icdPicker" data-remote-select data-url="{{ route('icd10.search') }}" data-label-field="name_id" data-secondary-field="code" placeholder="Cari kode / nama penyakit…" aria-label="Cari diagnosis ICD-10"></select>
                        </div>
                        <div class="col-md-3">
                          <select class="form-select" id="icdType" aria-label="Jenis diagnosis">
                            <option value="primary">Primer</option>
                            <option value="secondary" @selected($encounter->diagnoses->isNotEmpty())>Sekunder</option>
                          </select>
                        </div>
                        <div class="col-md-2 d-grid"><button type="button" class="btn btn-soft-primary" id="addDiagnosis"><i class="bi bi-plus-lg"></i></button></div>
                      </div>
                      <div id="diagnosisList" class="mt-2">@include('emr.partials.diagnoses', ['editable' => true])</div>
                    </div>
                  @endif

                  <textarea class="form-control js-soap" id="{{ $field }}" name="{{ $field }}" rows="{{ $field === 'assessment' ? 2 : 3 }}"
                            placeholder="{{ $field === 'assessment' ? 'Catatan assessment (opsional)' : '' }}">{{ old($field, $note->{$field}) }}</textarea>
                </div>
              @endforeach
            </div>
            <div class="card-footer d-flex gap-2">
              <button class="btn btn-soft-secondary" type="submit"><i class="bi bi-save me-1"></i>Simpan draf</button>
            </div>
          </div>
        </form>

        <div class="card">
          <div class="card-header"><h5 class="mb-0">Tindakan medis</h5></div>
          <div class="card-body">
            <div class="row g-2 mb-2">
              <div class="col-md-8">
                <select class="form-select" id="servicePicker" aria-label="Pilih tindakan">
                  <option value="">Pilih tindakan…</option>
                  @foreach ($serviceOptions as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-6 col-md-2"><input class="form-control nx-num" type="number" min="1" max="100" value="1" id="serviceQty" aria-label="Jumlah"></div>
              <div class="col-6 col-md-2 d-grid"><button type="button" class="btn btn-soft-primary" id="addService"><i class="bi bi-plus-lg"></i></button></div>
            </div>
            <div id="serviceList">@include('emr.partials.services', ['editable' => true])</div>
          </div>
        </div>
      @endif
    </div>

    {{-- ================= KANAN: e-Resep & penyelesaian (30%) ================= --}}
    <div class="nx-emr-right">
      <div class="nx-emr-sticky">
        <div class="card">
          <div class="card-header"><h5 class="mb-0">e-Resep</h5></div>
          <div class="card-body">
            @if ($patient->isChild() && $vital?->weight)
              <div class="nx-clinical-alert moderate mb-3"><i class="bi bi-person-arms-up"></i><div><span class="nx-ca-label d-block">Pasien anak</span>BB {{ str_replace('.', ',', $vital->weight) }} kg — acuan dosis.</div></div>
            @endif
            @if ($editable)
              <div id="rxForm" class="mb-3">
                <select id="drugPicker" data-url="{{ route('drugs.search') }}" placeholder="Cari obat / zat aktif…" aria-label="Cari obat"></select>
                <div id="drugInfo" class="small text-muted mt-1"></div>
                <div class="row g-2 mt-1">
                  <div class="col-5"><input class="form-control nx-num" type="number" min="1" id="rxQty" value="1" aria-label="Jumlah"></div>
                  <div class="col-7"><select class="form-select" id="rxPack" aria-label="Kemasan"></select></div>
                  <div class="col-12">
                    <input class="form-control" id="rxSigna" maxlength="120" placeholder="Aturan pakai, mis. 3 x 1 tablet sesudah makan" aria-label="Aturan pakai (signa)">
                    <div class="nx-signa-quick d-flex flex-wrap gap-1 mt-1">
                      @foreach (['3 x 1', '2 x 1', '1 x 1', '3 x 1/2'] as $freq)
                        <button type="button" class="btn btn-soft-secondary js-signa" data-freq="{{ $freq }}">{{ $freq }}</button>
                      @endforeach
                      <button type="button" class="btn btn-soft-secondary js-signa-when" data-when="sesudah makan">sesudah makan</button>
                      <button type="button" class="btn btn-soft-secondary js-signa-when" data-when="sebelum makan">sebelum makan</button>
                      <button type="button" class="btn btn-soft-secondary js-signa-when" data-when="bila perlu">bila perlu</button>
                    </div>
                  </div>
                  <div class="col-12"><input class="form-control form-control-sm" id="rxNote" maxlength="255" placeholder="Catatan untuk apoteker (opsional)" aria-label="Catatan"></div>
                  <div class="col-12 d-flex gap-2">
                    <button type="button" class="btn btn-soft-primary flex-grow-1" id="rxAdd"><i class="bi bi-plus-lg me-1"></i>Tambah ke resep</button>
                    <button type="button" class="btn btn-soft-secondary" id="rxCopy" title="Salin resep kunjungan terakhir"><i class="bi bi-files"></i><span class="d-none d-xxl-inline ms-1">Salin terakhir</span></button>
                  </div>
                </div>
              </div>
            @endif
            <div id="rxItems">@include('emr.partials.prescription-items', ['editable' => $editable])</div>
          </div>
        </div>

        @if ($editable || ($encounter->prescription?->items->isNotEmpty() ?? false))
          <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-shield-exclamation me-1"></i>Cek alergi &amp; interaksi</h5></div>
            <div class="card-body" id="cdsPanel">@include('emr.partials.cds-alerts', ['alerts' => $alerts, 'editable' => $editable, 'overrides' => $overrides])</div>
          </div>
        @endif

        @if ($editable)
          @php $hasRx = $encounter->prescription?->items->isNotEmpty() ?? false; @endphp
          <div class="card">
            <div class="card-header"><h5 class="mb-0">Selesaikan pemeriksaan</h5></div>
            <div class="card-body">
              <ul class="list-unstyled small mb-3" id="finishChecklist">
                <li data-check="subjective"><i class="bi bi-circle"></i> Subjective (keluhan) terisi</li>
                <li data-check="primary"><i class="bi bi-circle"></i> Diagnosis primer ICD-10</li>
                <li data-check="override"><i class="bi bi-circle"></i> Peringatan berat sudah dialasankan</li>
              </ul>
              <button type="submit" form="soapForm" formaction="{{ route('emr.finish', $encounter) }}" class="btn btn-primary w-100"
                      id="finishBtn" data-confirm-finish="Selesaikan pemeriksaan? Rekam medis akan dikunci; koreksi selanjutnya hanya lewat addendum.">
                <i class="bi bi-send-check me-1"></i><span id="finishLabel">{{ $hasRx ? 'Simpan & kirim ke farmasi' : 'Selesai & kirim ke kasir' }}</span>
              </button>
              <div class="form-text" id="finishHint">{{ $hasRx ? 'Resep masuk antrean verifikasi apoteker.' : 'Tanpa resep: pasien langsung ke kasir untuk pembayaran tindakan.' }}</div>
            </div>
          </div>
        @endif
      </div>
    </div>
  </div>

  {{-- Modal peringatan BERAT: menahan proses (PRD §11.1) --}}
  <div class="modal fade nx-modal-major" id="majorAlertModal" tabindex="-1" aria-labelledby="majorAlertTitle" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="majorAlertTitle"><i class="bi bi-x-octagon-fill me-1"></i>PERINGATAN BERAT</h5>
        </div>
        <div class="modal-body" id="majorAlertBody"></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-danger" id="majorRemove"><i class="bi bi-trash me-1"></i>Batalkan obat ini</button>
          <button type="button" class="btn btn-soft-secondary" id="majorKeep">Tetap resepkan (isi alasan)</button>
        </div>
      </div>
    </div>
  </div>

  {{-- Modal riwayat kunjungan --}}
  @foreach ($history as $h)
    <div class="modal fade" id="history{{ $h->id }}" tabindex="-1" aria-labelledby="historyTitle{{ $h->id }}" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="historyTitle{{ $h->id }}">{{ $h->registered_at->translatedFormat('d F Y') }} · {{ $h->poli->name }} · {{ $h->doctor?->name }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            @if ($h->latestVital)<div class="small mb-3"><span class="text-muted fw-semibold">Tanda vital:</span> {{ $h->latestVital->summary() }}</div>@endif
            @include('emr.partials.record', ['encounter' => $h])
          </div>
        </div>
      </div>
    </div>
  @endforeach
@endsection

@if ($editable)
  @push('scripts')
  <script>
  jQuery(function ($) {
    var $form = $('#soapForm');
    var dirty = false;

    function setState(text) { $('#saveState').text(text); }

    // Sisipkan template / TTV ke textarea di posisi kursor (atau di akhir).
    $(document).on('click', '[data-insert]', function () {
      var el = $($(this).data('insert'))[0];
      var text = String($(this).data('text'));
      var start = el.selectionStart ?? el.value.length;
      var before = el.value.slice(0, start);
      var sep = before && !/\s$/.test(before) ? '\n' : '';
      el.value = before + sep + text + el.value.slice(el.selectionEnd ?? start);
      $(el).trigger('input').trigger('focus');
    });

    // Autosimpan draf: 3 detik setelah berhenti mengetik.
    var timer;
    $form.on('input', '.js-soap', function () {
      dirty = true;
      setState('Belum tersimpan…');
      clearTimeout(timer);
      timer = setTimeout(save, 3000);
      updateChecklist();
    });
    function save() {
      if (!dirty) return $.Deferred().resolve();
      dirty = false;
      setState('Menyimpan…');
      return $.ajax({ url: $form.attr('action'), method: 'POST', data: $form.serialize(), headers: { Accept: 'application/json' } })
        .done(function (r) { setState('Draf tersimpan ' + r.saved_at); })
        .fail(function (x) { dirty = true; setState((x.responseJSON && x.responseJSON.message) || 'Gagal menyimpan — periksa koneksi'); });
    }
    $form.on('submit', function (e) {
      // Tombol "Simpan draf" disimpan lewat AJAX; tombol Selesai memakai submit biasa.
      var submitter = e.originalEvent && e.originalEvent.submitter;
      if (submitter && submitter.id === 'finishBtn') {
        if (!window.confirm($(submitter).data('confirm-finish'))) { e.preventDefault(); return; }
        dirty = false;
        return;
      }
      e.preventDefault();
      dirty = true;
      save();
    });
    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

    function post(url, data, target, method) {
      return $.ajax({ url: url, method: 'POST', data: $.extend({ _method: method || 'POST' }, data), headers: { Accept: 'application/json' } })
        .done(function (r) { $(target).html(r.html); updateChecklist(); })
        .fail(function (x) {
          var r = x.responseJSON || {};
          var msg = r.errors ? Object.values(r.errors)[0][0] : (r.message || 'Terjadi kesalahan.');
          window.alert(msg);
        });
    }

    $('#addDiagnosis').on('click', function () {
      var ts = document.getElementById('icdPicker').tomselect;
      if (!ts.getValue()) { ts.focus(); return; }
      post(@json(route('emr.diagnoses.store', $encounter)), { icd10_id: ts.getValue(), type: $('#icdType').val() }, '#diagnosisList')
        .done(function () { ts.clear(); $('#icdType').val('secondary'); });
    });
    $('#addService').on('click', function () {
      var id = $('#servicePicker').val();
      if (!id) { $('#servicePicker').trigger('focus'); return; }
      post(@json(route('emr.services.store', $encounter)), { medical_service_id: id, quantity: $('#serviceQty').val() }, '#serviceList')
        .done(function () { $('#servicePicker').val(''); $('#serviceQty').val(1); });
    });
    $(document).on('click', '.js-remove-item', function () {
      post($(this).data('url'), {}, $(this).data('target'), 'DELETE');
    });

    function updateChecklist() {
      var checks = {
        subjective: $.trim($('#subjective').val()) !== '',
        primary: $('#diagnosisList .badge-soft-primary').length > 0
      };
      $.each(checks, function (key, ok) {
        $('#finishChecklist [data-check="' + key + '"] i').attr('class', ok ? 'bi bi-check-circle-fill text-success' : 'bi bi-circle');
      });
    }
    updateChecklist();
  });
  </script>
  @endpush
@endif

@if ($editable)
  @push('scripts')
  <script>
  jQuery(function ($) {
    var esc = function (s) { return $('<span>').text(s == null ? '' : s).html(); };
    var knownMajor = {};
    $('#cdsPanel [data-alert-key]').each(function () { knownMajor[$(this).data('alert-key')] = true; });
    var itemIds = @json($encounter->prescription?->items->pluck('id', 'drug_id') ?? new stdClass);
    var pendingMajor = null;
    var majorModal = new bootstrap.Modal('#majorAlertModal');

    // ---- Pemilih obat: komposisi, golongan, stok, kemasan ----
    var picker = new TomSelect('#drugPicker', {
      valueField: 'id', labelField: 'name', searchField: ['name', 'composition'],
      maxOptions: 20, loadThrottle: 250,
      load: function (q, cb) { $.getJSON($('#drugPicker').data('url'), { q: q }).done(cb).fail(function () { cb(); }); },
      render: {
        option: function (d) {
          return '<div><div class="fw-semibold">' + esc(d.name) + ' <span class="badge ' + d.category_badge + '">' + esc(d.category_label) + '</span></div>' +
            '<div class="small text-muted">' + esc(d.composition) + ' · Stok: ' + esc(d.stock_label || '—') + '</div></div>';
        },
        no_results: function () { return '<div class="no-results">Obat tidak ditemukan.</div>'; }
      },
      onChange: function (id) {
        var d = this.options[id];
        var $pack = $('#rxPack').empty();
        if (!d) { $('#drugInfo').empty(); return; }
        d.packagings.forEach(function (p) {
          $pack.append($('<option>').val(p.id).text(p.unit_name + (p.quantity_in_base > 1 ? ' (' + p.quantity_in_base + ' ' + d.base_unit + ')' : '')).prop('selected', p.is_default));
        });
        $('#drugInfo').html(esc(d.composition) + ' · <span class="badge ' + d.category_badge + '">' + esc(d.category_label) + '</span> · Stok: ' + esc(d.stock_label || '—'));
        $('#rxQty').trigger('focus').trigger('select');
      }
    });

    // ---- Signa cepat: "3 x 1" + satuan kemasan + waktu ----
    $('.js-signa').on('click', function () {
      var d = picker.options[picker.getValue()];
      var unit = d ? d.base_unit : '';
      var when = ($('#rxSigna').val().match(/(sesudah makan|sebelum makan|bila perlu)$/) || [''])[0];
      $('#rxSigna').val($.trim($(this).data('freq') + ' ' + unit + ' ' + when));
    });
    $('.js-signa-when').on('click', function () {
      var base = $('#rxSigna').val().replace(/\s*(sesudah makan|sebelum makan|bila perlu)$/, '');
      $('#rxSigna').val($.trim(base + ' ' + $(this).data('when')));
    });

    // ---- Simpan alasan override yang sudah diketik saat panel dirender ulang ----
    function render(r) {
      var reasons = {};
      $('#cdsPanel .js-override').each(function () { reasons[$(this).data('key')] = this.value; });
      $('#rxItems').html(r.items_html);
      $('#cdsPanel').html(r.alerts_html);
      $('#cdsPanel .js-override').each(function () { if (reasons[$(this).data('key')]) this.value = reasons[$(this).data('key')]; });
      itemIds = r.item_ids || {};
      var hasRx = r.count > 0;
      $('#finishLabel').text(hasRx ? 'Simpan & kirim ke farmasi' : 'Selesai & kirim ke kasir');
      $('#finishHint').text(hasRx ? 'Resep masuk antrean verifikasi apoteker.' : 'Tanpa resep: pasien langsung ke kasir untuk pembayaran tindakan.');
      if (r.message) window.alert(r.message);

      // Peringatan BERAT yang baru muncul → dialog merah yang menahan proses.
      var fresh = (r.alerts || []).filter(function (a) { return a.requires_override && !knownMajor[a.key]; });
      var current = {};
      (r.alerts || []).forEach(function (a) { current[a.key] = true; });
      knownMajor = current;
      if (fresh.length) showMajor(fresh[0]);
      updateOverrideCheck();
    }

    function showMajor(a) {
      pendingMajor = a;
      $('#majorAlertBody').html(
        '<div class="nx-ca-label text-danger mb-1">' + esc(a.severity_label) + ' · ' + esc(a.type_label) + '</div>' +
        '<div class="fw-semibold fs-5 mb-2">' + esc(a.title) + '</div><p>' + esc(a.message) + '</p>' +
        (a.recommendation ? '<p class="text-muted mb-0">Saran: ' + esc(a.recommendation) + '</p>' : ''));
      majorModal.show();
    }
    $('#majorRemove').on('click', function () {
      // Batalkan obat yang terakhir memicu peringatan.
      var drugId = pendingMajor.drug_ids[pendingMajor.drug_ids.length - 1];
      var itemId = itemIds[drugId];
      majorModal.hide();
      if (itemId) send(@json(url('ruang-periksa/'.$encounter->id.'/resep')) + '/' + itemId, 'DELETE');
    });
    $('#majorKeep').on('click', function () {
      majorModal.hide();
      var $ta = $('#cdsPanel .js-override[data-key="' + pendingMajor.key + '"]');
      setTimeout(function () { $ta[0] && $ta[0].scrollIntoView({ block: 'center' }); $ta.trigger('focus'); }, 300);
    });

    function send(url, method, data) {
      return $.ajax({ url: url, method: 'POST', data: $.extend({ _method: method }, data || {}), headers: { Accept: 'application/json' } })
        .done(render)
        .fail(function (x) {
          var r = x.responseJSON || {};
          window.alert(r.errors ? Object.values(r.errors)[0][0] : (r.message || 'Terjadi kesalahan.'));
        });
    }

    $('#rxAdd').on('click', function () {
      if (!picker.getValue()) { picker.focus(); return; }
      if (!$.trim($('#rxSigna').val())) { $('#rxSigna').trigger('focus'); return; }
      send(@json(route('emr.prescription.store', $encounter)), 'POST', {
        drug_id: picker.getValue(), drug_packaging_id: $('#rxPack').val(), quantity: $('#rxQty').val(),
        signa: $('#rxSigna').val(), note: $('#rxNote').val()
      }).done(function () { picker.clear(); $('#rxPack').empty(); $('#rxQty').val(1); $('#rxSigna, #rxNote').val(''); $('#drugInfo').empty(); });
    });
    $('#rxSigna').on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); $('#rxAdd').trigger('click'); } });
    $('#rxCopy').on('click', function () { send(@json(route('emr.prescription.copy', $encounter)), 'POST'); });
    $(document).on('click', '.js-rx-remove', function () { send($(this).data('url'), 'DELETE'); });

    function updateOverrideCheck() {
      var ok = true;
      $('#cdsPanel .js-override').each(function () { if ($.trim(this.value).length < 10) ok = false; });
      $('#finishChecklist [data-check="override"] i').attr('class', ok ? 'bi bi-check-circle-fill text-success' : 'bi bi-exclamation-octagon-fill text-danger');
    }
    $(document).on('input', '#cdsPanel .js-override', updateOverrideCheck);
    updateOverrideCheck();
  });
  </script>
  @endpush
@endif
