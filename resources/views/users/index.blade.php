@extends('layouts.app')

@section('title', 'Users')

@section('breadcrumb')
    <li class="breadcrumb-item">System</li>
    <li class="breadcrumb-item active" aria-current="page">Users</li>
@endsection

@section('actions')
    @can('create', App\Models\User::class)
        <a href="{{ route('users.create') }}" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i> Add User</a>
    @endcan
@endsection

@section('content')
    <div class="card">
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('users.index') }}" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label for="search" class="form-label small mb-1">Search</label>
                    <input type="search" id="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Name, username, email or phone">
                </div>
                <div class="col-sm-6 col-md-3">
                    <label for="role" class="form-label small mb-1">Role</label>
                    <select id="role" name="role" class="form-select">
                        <option value="">All roles</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected((string) request('role') === (string) $role->id)>{{ $role->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label for="status" class="form-label small mb-1">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="bi bi-search"></i> Filter</button>
                    @if (request()->hasAny(['search', 'role', 'status']))
                        <a href="{{ route('users.index') }}" class="btn btn-light" title="Clear filters"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th class="d-none d-md-table-cell">Phone</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th class="d-none d-lg-table-cell">Last Login</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar">{{ $user->initials() }}</span>
                                <div class="min-w-0">
                                    <div class="fw-medium text-truncate">
                                        {{ $user->name }}
                                        @if (auth()->user()->is($user))<span class="badge text-bg-light border ms-1">You</span>@endif
                                    </div>
                                    <div class="small text-body-secondary text-truncate">{{ '@' . $user->username }} · {{ $user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="d-none d-md-table-cell">{{ $user->phone ?: '—' }}</td>
                        <td><span class="badge text-bg-primary-subtle text-primary-emphasis">{{ $user->role?->display_name }}</span></td>
                        <td>
                            @if ($user->is_active)
                                <span class="badge text-bg-success-subtle text-success-emphasis">Active</span>
                            @else
                                <span class="badge text-bg-secondary-subtle text-secondary-emphasis">Inactive</span>
                            @endif
                        </td>
                        <td class="d-none d-lg-table-cell small text-body-secondary">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                        <td class="text-end text-nowrap">
                            @can('update', $user)
                                <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-light" title="Edit"><i class="bi bi-pencil"></i></a>
                            @endcan
                            @can('toggleStatus', $user)
                                <form method="POST" action="{{ route('users.toggle-status', $user) }}" class="d-inline"
                                      data-confirm="{{ $user->is_active ? "Deactivate {$user->name}? They will be logged out and cannot sign in." : "Activate {$user->name}?" }}"
                                      data-confirm-button="{{ $user->is_active ? 'Deactivate' : 'Activate' }}"
                                      data-confirm-color="{{ $user->is_active ? '#fd7e14' : '#198754' }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-light" title="{{ $user->is_active ? 'Deactivate' : 'Activate' }}">
                                        <i class="bi bi-{{ $user->is_active ? 'pause-circle' : 'play-circle' }}"></i>
                                    </button>
                                </form>
                            @endcan
                            @can('delete', $user)
                                <form method="POST" action="{{ route('users.destroy', $user) }}" class="d-inline"
                                      data-confirm="Delete {{ $user->name }}? Their name stays on past records." data-confirm-button="Delete">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light text-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-body-secondary py-5">No users found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="card-footer bg-white">{{ $users->links() }}</div>
        @endif
    </div>
@endsection
