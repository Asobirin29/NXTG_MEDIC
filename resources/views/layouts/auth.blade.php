<!doctype html>
<html lang="id" data-nx-brand="{{ $brandTheme }}">
<head>
  @include('layouts.partials.head')
</head>
<body>
  <div class="nx-auth">
    <aside class="nx-auth-brand">
      <div class="nx-brand-row text-white">
        <img src="{{ asset('assets/img/logo.svg') }}" alt=""><span>{{ $brandName ?? config('app.name') }}</span>
      </div>
      <div>
        @yield('brand')
      </div>
      <div class="small" style="opacity:.8">
        Rekam Medis Elektronik &amp; Farmasi terpadu untuk puskesmas dan klinik.
      </div>
    </aside>

    <main class="nx-auth-form-wrap">
      <div class="nx-auth-card">
        <div class="d-lg-none d-flex align-items-center gap-2 mb-4">
          <img src="{{ asset('assets/img/logo.svg') }}" alt="" width="32" height="32"><span class="h4 mb-0">{{ $brandName ?? config('app.name') }}</span>
        </div>
        @yield('content')
      </div>
    </main>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="{{ asset('assets/js/app.js') }}"></script>
  <script src="{{ asset('assets/js/medic.js') }}"></script>
</body>
</html>
