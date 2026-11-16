<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.theme-script')
    <title>@yield('title', 'Dashboard') · {{ $businessName }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>
<div class="app-shell">
    @include('partials.sidebar')

    <div class="app-main">
        @include('partials.topbar')

        <main class="app-content">
            <div class="page-header">
                <div>
                    <h1 class="page-title">@yield('title', 'Dashboard')</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                            @yield('breadcrumb')
                        </ol>
                    </nav>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @yield('actions')
                </div>
            </div>

            @include('partials.flash')

            @yield('content')
        </main>

        <footer class="app-footer">
            &copy; {{ now()->year }} {{ $businessName }} · Inventory Management System
        </footer>
    </div>
</div>

@stack('scripts')
</body>
</html>
