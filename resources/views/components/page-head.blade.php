@props(['title', 'subtitle' => null, 'crumbs' => []])

<div class="nx-page-head d-flex flex-wrap align-items-end gap-3">
  <div class="me-auto">
    <nav class="nx-breadcrumb" aria-label="Breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Beranda</a></li>
        @foreach ($crumbs as $label => $url)
          @if (is_string($label))
            <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
          @else
            <li class="breadcrumb-item">{{ $url }}</li>
          @endif
        @endforeach
        <li class="breadcrumb-item active" aria-current="page">{{ $title }}</li>
      </ol>
    </nav>
    <h1>{{ $title }}</h1>
    @if ($subtitle)
      <p class="text-muted mb-0">{{ $subtitle }}</p>
    @endif
  </div>
  {{ $slot }}
</div>
