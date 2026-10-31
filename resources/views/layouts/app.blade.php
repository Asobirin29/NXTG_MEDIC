<!doctype html>
<html lang="id" data-nx-brand="{{ $brandTheme }}">
<head>
  @include('layouts.partials.head')
</head>
<body data-nx-layout="app" @isset($quickAction) data-quick-action="{{ $quickAction['label'] }}" data-quick-action-href="{{ $quickAction['url'] }}" @endisset>

  <a class="nx-skip-link" href="#nxContent">Lewati ke konten utama</a>

  @include('layouts.partials.sidebar')

  <div class="nx-sidebar-backdrop" id="nxSidebarBackdrop"></div>

  <div class="nx-main">
    @include('layouts.partials.header')

    <main class="nx-content" id="nxContent" tabindex="-1">
      @include('layouts.partials.flash')
      @yield('content')
    </main>

    <footer class="nx-footer">
      <div>&copy; {{ date('Y') }} {{ config('app.name') }} &mdash; {{ $authUser->tenant->name }}</div>
      <div class="text-muted small">Alat bantu keputusan klinis. Keputusan akhir tetap pada tenaga kesehatan.</div>
    </footer>
  </div>

  <script>window.NX_PALETTE = @json($palette);</script>
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/simplebar@6.2.7/dist/simplebar.min.js"></script>
  <script src="{{ asset('assets/js/app.js') }}"></script>
  <script src="{{ asset('assets/js/medic.js') }}"></script>
  @stack('scripts')
</body>
</html>
