<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $title ?? 'Cetak' }} | {{ config('app.name') }}</title>
  <style>
    :root { --paper: {{ $paper ?? '80mm' }}; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #e9ecef; color: #000; font: 12px/1.35 "Courier New", ui-monospace, monospace; }
    .toolbar { display: flex; gap: 8px; justify-content: center; padding: 12px; font-family: system-ui, sans-serif; }
    .toolbar a, .toolbar button { font: inherit; padding: 6px 12px; border: 1px solid #adb5bd; border-radius: 6px; background: #fff; color: #212529; text-decoration: none; cursor: pointer; }
    .toolbar .on { background: #212529; color: #fff; }
    .paper { width: var(--paper); margin: 0 auto 24px; background: #fff; padding: 4mm; }
    .center { text-align: center; } .right { text-align: right; } .bold { font-weight: 700; }
    .row { display: flex; justify-content: space-between; gap: 8px; }
    hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
    table { width: 100%; border-collapse: collapse; }
    td { vertical-align: top; padding: 1px 0; }
    @yield('styles')
    @media print {
      body { background: #fff; }
      .toolbar { display: none; }
      .paper { margin: 0; padding: 0 2mm; width: auto; }
      @page { size: var(--paper) auto; margin: 2mm; }
    }
  </style>
</head>
<body>
  <div class="toolbar">
    <button type="button" onclick="window.print()">Cetak</button>
    @yield('toolbar')
  </div>
  @yield('content')
  <script>if (new URLSearchParams(location.search).has('auto')) window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
