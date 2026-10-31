@if (session('success'))
  <div class="alert alert-success d-flex align-items-center gap-2" role="status">
    <i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span>
  </div>
@endif
@if (session('error'))
  <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
    <i class="bi bi-x-octagon-fill"></i><span>{{ session('error') }}</span>
  </div>
@endif
@if (session('warning'))
  <div class="alert alert-warning d-flex align-items-center gap-2" role="status">
    <i class="bi bi-exclamation-triangle-fill"></i><span>{{ session('warning') }}</span>
  </div>
@endif
