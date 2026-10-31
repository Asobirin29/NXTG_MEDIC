<!doctype html>
<html lang="id" data-nx-brand="indigo">
<head>
  @include('layouts.partials.head')
</head>
<body data-nx-layout="app">
  <a class="nx-skip-link" href="#nxContent">Lewati ke konten utama</a>

  <aside class="nx-sidebar" id="nxSidebar" aria-label="Navigasi platform">
    <div class="nx-sidebar-head">
      <a class="nx-sidebar-brand" href="{{ route('platform.tenants.index') }}">
        <img src="{{ asset('assets/img/logo.svg') }}" alt="">
        <span class="nx-brand-text">Admin Platform</span>
      </a>
      <button class="nx-icon-btn nx-sidebar-close" id="nxSidebarClose" type="button" aria-label="Tutup navigasi"><i class="bi bi-x-lg"></i></button>
    </div>
    <nav class="nx-sidebar-nav" id="nxSidebarNav">
      <div class="nx-menu-section has-active">
        <button class="nx-menu-group" type="button" data-bs-toggle="collapse" data-bs-target="#nxSecPlatform" aria-expanded="true" aria-controls="nxSecPlatform">
          <span class="nx-menu-group-text">Platform</span><i class="bi bi-chevron-down nx-group-chevron"></i>
        </button>
        <div class="collapse nx-section-body show" id="nxSecPlatform">
          <div class="nx-menu-item">
            <a class="nx-menu-link {{ request()->routeIs('platform.tenants.*') ? 'active' : '' }}" href="{{ route('platform.tenants.index') }}" data-title="Faskes">
              <i class="bi bi-hospital"></i><span class="nx-menu-label">Faskes</span>
            </a>
          </div>
        </div>
      </div>
    </nav>
    <div class="nx-sidebar-foot">
      <div class="nx-sidebar-user">
        <span class="nx-avatar-initial" aria-hidden="true"><i class="bi bi-shield-lock"></i></span>
        <span class="nx-user-meta">
          <span class="nx-user-name d-block">{{ auth('platform')->user()->name }}</span>
          <span class="nx-user-role">Super Admin</span>
        </span>
        <form method="POST" action="{{ route('platform.logout') }}" class="m-0">
          @csrf
          <button type="submit" class="nx-icon-btn" aria-label="Keluar"><i class="bi bi-box-arrow-right"></i></button>
        </form>
      </div>
    </div>
  </aside>
  <div class="nx-sidebar-backdrop" id="nxSidebarBackdrop"></div>

  <div class="nx-main">
    <header class="nx-header">
      <button class="nx-icon-btn" id="nxSidebarToggle" type="button" aria-label="Buka/tutup sidebar"><i class="bi bi-list"></i></button>
      <span class="badge badge-soft-primary ms-2">Panel Admin Platform</span>
      <div class="ms-auto d-flex align-items-center gap-1">
        <button class="nx-icon-btn" id="nxThemeToggle" type="button" aria-label="Ganti mode gelap/terang"><i class="bi bi-moon-stars"></i></button>
      </div>
    </header>
    <main class="nx-content" id="nxContent" tabindex="-1">
      @include('layouts.partials.flash')
      @yield('content')
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/simplebar@6.2.7/dist/simplebar.min.js"></script>
  <script src="{{ asset('assets/js/app.js') }}"></script>
  <script src="{{ asset('assets/js/medic.js') }}"></script>
</body>
</html>
