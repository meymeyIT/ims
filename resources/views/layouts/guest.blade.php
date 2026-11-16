<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.theme-script')
    <title>@yield('title', 'Sign in') · {{ $businessName }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-body">
<button class="btn btn-light theme-toggle-floating" type="button" data-theme-toggle aria-label="Switch to dark mode" title="Switch to dark mode">
    <i class="bi bi-moon-stars fs-5"></i>
</button>
<div class="auth-wrapper">
    <div class="auth-card card">
        <div class="card-body p-4 p-sm-5">
            <div class="d-flex align-items-center gap-2 mb-4">
                @if ($businessLogo)<img src="{{ $businessLogo }}" alt="" class="brand-logo brand-logo-lg">@else<span class="brand-mark"><i class="bi bi-box-seam"></i></span>@endif
                <div>
                    <div class="fw-semibold lh-sm">{{ $businessName }}</div>
                    <div class="small text-body-secondary">Inventory Management System</div>
                </div>
            </div>

            @yield('content')
        </div>
    </div>
    <p class="text-center small text-body-secondary mt-4 mb-0">&copy; {{ now()->year }} {{ $businessName }}</p>
</div>
</body>
</html>
