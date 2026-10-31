<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? 'NXTG Medic' }} | {{ config('app.name') }}</title>
<link rel="icon" href="{{ asset('assets/img/logo.svg') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/simplebar@6.2.7/dist/simplebar.min.css" rel="stylesheet">
<link href="{{ asset('assets/css/variables.css') }}" rel="stylesheet">
<link href="{{ asset('assets/css/layout.css') }}" rel="stylesheet">
<link href="{{ asset('assets/css/components.css') }}" rel="stylesheet">
<link href="{{ asset('assets/css/pages.css') }}" rel="stylesheet">
<link href="{{ asset('assets/css/motion.css') }}" rel="stylesheet">
<link href="{{ asset('assets/css/dark.css') }}" rel="stylesheet">
<link href="{{ asset('assets/css/theme-teal.css') }}" rel="stylesheet">
<link href="{{ asset('assets/css/medic.css') }}" rel="stylesheet">
@stack('styles')
<script src="{{ asset('assets/js/theme-init.js') }}"></script>
