<header class="nx-header">
  <button class="nx-icon-btn" id="nxSidebarToggle" type="button" aria-label="Buka/tutup sidebar">
    <i class="bi bi-list"></i>
  </button>

  <button class="nx-search-trigger d-none d-md-flex" id="nxPaletteTrigger" type="button" aria-label="Buka pencarian cepat">
    <i class="bi bi-search"></i>
    <span>Cari menu&hellip;</span>
    <span class="nx-kbd">Ctrl K</span>
  </button>
  <button class="nx-icon-btn d-md-none" id="nxPaletteTriggerSm" type="button" aria-label="Buka pencarian cepat">
    <i class="bi bi-search"></i>
  </button>

  <span class="nx-tenant-chip d-none d-xl-inline-flex ms-2" title="Faskes aktif">
    <i class="bi bi-hospital"></i>{{ $authUser->tenant->name }}
  </span>

  <div class="ms-auto d-flex align-items-center gap-1">
    @isset($quickAction)
      <button class="btn btn-primary btn-sm d-none d-sm-inline-flex align-items-center gap-1 me-1" id="nxQuickAction" type="button">
        <i class="bi bi-plus-lg"></i><span>{{ $quickAction['label'] }}</span>
      </button>
    @endisset

    <button class="nx-icon-btn" id="nxThemeToggle" type="button" aria-label="Ganti mode gelap/terang">
      <i class="bi bi-moon-stars"></i>
    </button>

    <button class="nx-icon-btn d-none d-md-inline-flex" id="nxFullscreen" type="button" aria-label="Layar penuh">
      <i class="bi bi-arrows-fullscreen"></i>
    </button>

    <div class="dropdown">
      <button class="nx-icon-btn" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notifikasi">
        <i class="bi bi-bell"></i>
      </button>
      <div class="dropdown-menu dropdown-menu-end nx-dropdown-notif">
        <div class="nx-notif-head">
          <h5 class="mb-0">Notifikasi</h5>
        </div>
        <div class="text-center text-muted small py-4 px-3">
          <i class="bi bi-bell-slash d-block fs-4 mb-2"></i>Belum ada notifikasi.
        </div>
      </div>
    </div>

    <div class="dropdown">
      <a class="nx-header-user" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menu pengguna">
        <span class="nx-avatar-initial" aria-hidden="true">{{ $authUser->initials() }}</span>
        <span class="nx-user-meta-wrap d-none d-lg-block">
          <span class="nx-user-name d-block">{{ $authUser->name }}</span>
          <span class="nx-user-role">{{ $authUser->role->label() }}</span>
        </span>
      </a>
      <div class="dropdown-menu dropdown-menu-end" style="min-width:220px">
        <a class="dropdown-item" href="{{ route('switch-user') }}"><i class="bi bi-people"></i>Ganti pengguna (PIN)</a>
        @can('settings.manage')
          <a class="dropdown-item" href="{{ route('settings.appearance') }}"><i class="bi bi-palette"></i>Tampilan</a>
        @endcan
        <div class="dropdown-divider"></div>
        <form method="POST" action="{{ route('logout') }}" class="m-0">
          @csrf
          <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right"></i>Keluar</button>
        </form>
      </div>
    </div>
  </div>
</header>
