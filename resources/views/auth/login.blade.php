@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <h1 class="h4 fw-semibold mb-1">Welcome back</h1>
    <p class="text-body-secondary mb-4">Sign in to manage your inventory.</p>

    @if (session('status'))
        <div class="alert alert-success small">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label for="login" class="form-label">Username or email</label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input id="login" type="text" name="login" value="{{ old('login') }}"
                       class="form-control @error('login') is-invalid @enderror"
                       required autofocus autocomplete="username" autocapitalize="none" spellcheck="false">
                @error('login')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <div class="input-group has-validation">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input id="password" type="password" name="password"
                       class="form-control @error('password') is-invalid @enderror"
                       required autocomplete="current-password">
                <button class="btn btn-outline-secondary" type="button" data-toggle-password="#password" aria-label="Show password">
                    <i class="bi bi-eye"></i>
                </button>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" @checked(old('remember'))>
                <label class="form-check-label" for="remember">Remember me</label>
            </div>
            <a href="{{ route('password.request') }}" class="small">Forgot password?</a>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2">
            <i class="bi bi-box-arrow-in-right me-1"></i> Sign in
        </button>
    </form>
@endsection
