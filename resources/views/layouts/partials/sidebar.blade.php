<aside class="nx-sidebar" id="nxSidebar" aria-label="Navigasi utama">
  <div class="nx-sidebar-head">
    <a class="nx-sidebar-brand" href="{{ route('home') }}">
      <img src="{{ asset('assets/img/logo.svg') }}" alt="Logo {{ config('app.name') }}">
      <span class="nx-brand-text">{{ config('app.name') }}</span>
    </a>
    <button class="nx-icon-btn nx-sidebar-close" id="nxSidebarClose" type="button" aria-label="Tutup navigasi">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <div class="nx-sidebar-search">
    <i class="bi bi-search"></i>
    <input type="search" id="nxMenuFilter" class="nx-sidebar-search-input"
           placeholder="Filter menu&hellip;" autocomplete="off" aria-label="Filter menu"
           aria-controls="nxSidebarNav">
    <button type="button" class="nx-sidebar-search-clear" id="nxMenuFilterClear" aria-label="Hapus filter" hidden>
      <i class="bi bi-x"></i>
    </button>
  </div>

  <nav class="nx-sidebar-nav" id="nxSidebarNav">
    @foreach ($navSections as $section)
      <div class="nx-menu-section {{ $section['active'] ? 'has-active' : '' }}">
        <button class="nx-menu-group" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $section['id'] }}"
                aria-expanded="{{ $section['active'] ? 'true' : 'false' }}" aria-controls="{{ $section['id'] }}">
          <span class="nx-menu-group-text">{{ $section['label'] }}</span><i class="bi bi-chevron-down nx-group-chevron"></i>
        </button>
        <div class="collapse nx-section-body {{ $section['active'] ? 'show' : '' }}" id="{{ $section['id'] }}">
          @foreach ($section['items'] as $item)
            @if ($item['children'])
              <div class="nx-menu-item has-submenu">
                <a class="nx-menu-link {{ $item['active'] ? 'active' : '' }}" data-bs-toggle="collapse" href="#{{ $item['id'] }}" role="button"
                   aria-expanded="{{ $item['active'] ? 'true' : 'false' }}" data-title="{{ $item['label'] }}">
                  <i class="bi {{ $item['icon'] }}"></i><span class="nx-menu-label">{{ $item['label'] }}</span><i class="bi bi-chevron-right nx-menu-chevron"></i>
                </a>
                <ul class="collapse nx-submenu {{ $item['active'] ? 'show' : '' }}" id="{{ $item['id'] }}">
                  @foreach ($item['children'] as $child)
                    <li><a class="nx-menu-link {{ $child['active'] ? 'active' : '' }}" href="{{ $child['url'] }}" @if ($child['active']) aria-current="page" @endif>{{ $child['label'] }}</a></li>
                  @endforeach
                </ul>
              </div>
            @else
              <div class="nx-menu-item">
                <a class="nx-menu-link {{ $item['active'] ? 'active' : '' }}" href="{{ $item['url'] }}" data-title="{{ $item['label'] }}" @if ($item['active']) aria-current="page" @endif>
                  <i class="bi {{ $item['icon'] }}"></i><span class="nx-menu-label">{{ $item['label'] }}</span>
                </a>
              </div>
            @endif
          @endforeach
        </div>
      </div>
    @endforeach
  </nav>

  <div class="nx-sidebar-foot">
    <div class="nx-sidebar-user">
      <span class="nx-avatar-initial" aria-hidden="true">{{ $authUser->initials() }}</span>
      <span class="nx-user-meta">
        <span class="nx-user-name d-block">{{ $authUser->name }}</span>
        <span class="nx-user-role">{{ $authUser->role->label() }}</span>
      </span>
      <form method="POST" action="{{ route('logout') }}" class="m-0">
        @csrf
        <button type="submit" class="nx-icon-btn" aria-label="Keluar"><i class="bi bi-box-arrow-right"></i></button>
      </form>
    </div>
  </div>
</aside>
