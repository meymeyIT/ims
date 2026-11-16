@extends('layouts.app')

@section('title', 'My Profile')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Profile</li>
@endsection

@section('content')
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white"><h2 class="h6 mb-0">Profile information</h2></div>
                <form method="POST" action="{{ route('profile.update') }}" novalidate>
                    @csrf
                    @method('PATCH')
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Full name</label>
                            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}"
                                   class="form-control @error('name', 'updateProfile') is-invalid @enderror" required>
                            @error('name', 'updateProfile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}"
                                   class="form-control @error('email', 'updateProfile') is-invalid @enderror" required autocomplete="username">
                            @error('email', 'updateProfile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input id="phone" name="phone" type="tel" value="{{ old('phone', $user->phone) }}"
                                   class="form-control @error('phone', 'updateProfile') is-invalid @enderror">
                            @error('phone', 'updateProfile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="username_display">Username</label>
                            <input id="username_display" type="text" class="form-control" value="{{ $user->username }}" disabled>
                            <div class="form-text">Ask an administrator to change your username.</div>
                        </div>
                        <div class="small text-body-secondary">
                            Role: <strong>{{ $user->role?->display_name }}</strong>
                            · Last login: {{ $user->last_login_at?->toAppDateTime() ?? '—' }}
                        </div>
                    </div>
                    <div class="card-footer bg-white text-end">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white"><h2 class="h6 mb-0">Change password</h2></div>
                <form method="POST" action="{{ route('profile.password') }}" novalidate>
                    @csrf
                    @method('PUT')
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current password</label>
                            <input id="current_password" name="current_password" type="password"
                                   class="form-control @error('current_password', 'updatePassword') is-invalid @enderror" autocomplete="current-password">
                            @error('current_password', 'updatePassword')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New password</label>
                            <div class="input-group has-validation">
                                <input id="new_password" name="password" type="password"
                                       class="form-control @error('password', 'updatePassword') is-invalid @enderror" autocomplete="new-password">
                                <button class="btn btn-outline-secondary" type="button" data-toggle-password="#new_password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                                @error('password', 'updatePassword')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-text">Min. 8 characters with upper & lower case letters and a number.</div>
                        </div>
                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirm new password</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="card-footer bg-white text-end">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-key me-1"></i> Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
