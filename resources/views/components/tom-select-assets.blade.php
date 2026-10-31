@once
  @push('styles')
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.4.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
  @endpush
  @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.1/dist/js/tom-select.complete.min.js"></script>
    <script>jQuery(function () { nxInitRemoteSelects(document); });</script>
  @endpush
@endonce
