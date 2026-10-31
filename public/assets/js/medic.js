/* ============================================================
   NXTG Medic — perilaku global kecil di atas app.js NexaDash.
   ============================================================ */
(function ($) {
  'use strict';

  // Token CSRF untuk semua request AJAX jQuery.
  $.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
  });

  // Tampilkan/sembunyikan password: <button class="btn-toggle-pw" data-target="#id">.
  $(document).on('click', '.btn-toggle-pw', function () {
    var $input = $($(this).data('target'));
    var show = $input.attr('type') === 'password';
    $input.attr('type', show ? 'text' : 'password');
    $(this).find('i').toggleClass('bi-eye', !show).toggleClass('bi-eye-slash', show);
    $(this).attr('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
  });

  // Konfirmasi sebelum submit: <form data-confirm="Pesan?">.
  $(document).on('submit', 'form[data-confirm]', function (e) {
    if (!window.confirm($(this).data('confirm'))) e.preventDefault();
  });

  // Cegah submit ganda: tombol submit dinonaktifkan setelah diklik.
  $(document).on('submit', 'form:not([data-allow-resubmit])', function (e) {
    if (e.isDefaultPrevented()) return;
    var $btn = $(this).find('[type="submit"]');
    setTimeout(function () { $btn.prop('disabled', true); }, 0);
  });

  /* Pemilih dengan pencarian ke server (Tom Select):
     <select data-remote-select data-url="..." data-label-field="name"
             data-secondary-field="code"><option value="1" selected>…</option></select> */
  window.nxInitRemoteSelects = function (root) {
    if (typeof TomSelect === 'undefined') return;
    $(root).find('select[data-remote-select]').each(function () {
      if (this.tomselect) return;
      var $el = $(this);
      var label = $el.data('label-field') || 'name';
      var secondary = $el.data('secondary-field');
      var esc = function (s) { return $('<span>').text(s == null ? '' : s).html(); };
      new TomSelect(this, {
        valueField: 'id',
        labelField: label,
        searchField: [label].concat(secondary ? [secondary] : []),
        maxOptions: 20,
        loadThrottle: 250,
        placeholder: $el.attr('placeholder') || 'Ketik untuk mencari…',
        load: function (query, callback) {
          $.getJSON($el.data('url'), { q: query }).done(callback).fail(function () { callback(); });
        },
        render: {
          option: function (item) {
            return '<div>' + esc(item[label]) + (secondary && item[secondary] ? ' <span class="text-muted small">' + esc(item[secondary]) + '</span>' : '') + '</div>';
          },
          no_results: function () { return '<div class="no-results">Tidak ditemukan.</div>'; }
        }
      });
    });
  };

  // Pratinjau tema langsung saat admin memilih opsi tema.
  $(document).on('change', 'input[name="theme"]', function () {
    document.documentElement.setAttribute('data-nx-brand', this.value);
    document.dispatchEvent(new CustomEvent('nx:theme-changed', { detail: { theme: window.nxTheme ? nxTheme() : 'light' } }));
  });
})(jQuery);
