@extends('layouts.app')

@section('title', 'Roles & Permissions')

@section('breadcrumb')
    <li class="breadcrumb-item">System</li>
    <li class="breadcrumb-item active" aria-current="page">Roles</li>
@endsection

@section('content')
    <div class="row g-3">
        @foreach ($roles as $role)
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="stat-icon bg-primary-subtle text-primary-emphasis"><i class="bi bi-shield-lock"></i></span>
                            <h2 class="h6 mb-0">{{ $role->display_name }}</h2>
                        </div>
                        <p class="small text-body-secondary flex-grow-1">{{ $role->description }}</p>
                        <div class="d-flex justify-content-between small mb-3">
                            <span><i class="bi bi-people me-1"></i>{{ $role->users_count }} {{ Str::plural('user', $role->users_count) }}</span>
                            <span>
                                <i class="bi bi-key me-1"></i>
                                @if ($role->name === App\Models\Role::ADMIN)
                                    All permissions
                                @else
                                    {{ $role->permissions_count }} / {{ $totalPermissions }} permissions
                                @endif
                            </span>
                        </div>
                        @if ($role->name !== App\Models\Role::ADMIN)
                            @can('roles.manage')
                                <a href="{{ route('roles.edit', $role) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i> Edit permissions</a>
                            @endcan
                        @else
                            <span class="btn btn-light btn-sm disabled" aria-disabled="true"><i class="bi bi-lock me-1"></i> Always full access</span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
