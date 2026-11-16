@extends('layouts.app')

@section('title', "Permissions: {$role->display_name}")

@section('breadcrumb')
    <li class="breadcrumb-item">System</li>
    <li class="breadcrumb-item"><a href="{{ route('roles.index') }}">Roles</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $role->display_name }}</li>
@endsection

@section('content')
    <form method="POST" action="{{ route('roles.update', $role) }}">
        @csrf
        @method('PUT')

        @error('permissions')<div class="alert alert-danger">{{ $message }}</div>@enderror

        <div class="row g-3">
            @foreach ($permissionsByModule as $module => $permissions)
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <span class="fw-semibold text-capitalize">{{ str_replace('_', ' ', $module) }}</span>
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" id="all-{{ $module }}" data-check-all="{{ $module }}">
                                <label class="form-check-label small" for="all-{{ $module }}">All</label>
                            </div>
                        </div>
                        <div class="card-body">
                            @foreach ($permissions as $permission)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           value="{{ $permission->id }}" id="perm-{{ $permission->id }}"
                                           data-module="{{ $module }}"
                                           @checked(in_array($permission->id, old('permissions', $granted)))>
                                    <label class="form-check-label" for="perm-{{ $permission->id }}">
                                        {{ $permission->display_name }}
                                        <code class="small text-body-secondary d-block">{{ $permission->name }}</code>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('roles.index') }}" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save Permissions</button>
        </div>
    </form>
@endsection
