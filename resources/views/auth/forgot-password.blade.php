@extends('layouts.guest')

@section('title', 'Forgot password')

@section('content')
    <h1 class="h4 fw-semibold mb-1">Forgot your password?</h1>
    <p class="text-body-secondary mb-4">Enter your email and we'll send you a link to choose a new password.</p>

    @if (session('status'))
        <div class="alert alert-success small">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf

        <div class="mb-4">
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror" required autofocus>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2">Send reset link</button>
    </form>

    <div class="text-center mt-4">
        <a href="{{ route('login') }}" class="small"><i class="bi bi-arrow-left"></i> Back to sign in</a>
    </div>
@endsection
