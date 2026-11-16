@extends('layouts.guest')

@section('title', 'Access denied')

@section('content')
    <div class="text-center">
        <span class="stat-icon bg-danger-subtle text-danger-emphasis mx-auto mb-3" style="width:3.5rem;height:3.5rem;font-size:1.5rem">
            <i class="bi bi-shield-x"></i>
        </span>
        <h1 class="h4 fw-semibold">Access denied</h1>
        <p class="text-body-secondary">You don't have permission to open this page. Ask an administrator if you need access.</p>
        @auth
            <a href="{{ route('dashboard') }}" class="btn btn-primary"><i class="bi bi-house me-1"></i> Back to dashboard</a>
        @else
            <a href="{{ route('login') }}" class="btn btn-primary">Sign in</a>
        @endauth
    </div>
@endsection
