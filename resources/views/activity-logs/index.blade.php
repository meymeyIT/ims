@extends('layouts.app')

@section('title', 'Activity Logs')

@section('breadcrumb')
    <li class="breadcrumb-item">System</li>
    <li class="breadcrumb-item active" aria-current="page">Activity Logs</li>
@endsection

@php
    // Colour per kind of action (always shown with the action name, never colour alone).
    $actionColor = fn (string $action) => match (true) {
        str_starts_with($action, 'login_failed'), str_starts_with($action, 'login_locked'), str_starts_with($action, 'login_blocked'), $action === 'deleted' => 'danger',
        in_array($action, ['created', 'stock_in', 'received', 'approved', 'restored', 'activated'], true) => 'success',
        in_array($action, ['updated', 'adjusted', 'submitted', 'password_changed', 'password_reset'], true) => 'primary',
        in_array($action, ['archived', 'cancelled', 'deactivated', 'reversed', 'stock_out'], true) => 'warning',
        default => 'secondary',
    };
@endphp

@section('content')
    <div class="card">
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('activity-logs.index') }}" class="row g-2 align-items-end">
                <div class="col-lg-3">
                    <label for="search" class="form-label small mb-1">Search</label>
                    <input type="search" id="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Description or IP address">
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="module" class="form-label small mb-1">Module</label>
                    <select id="module" name="module" class="form-select">
                        <option value="">All modules</option>
                        @foreach ($modules as $module)
                            <option value="{{ $module }}" @selected(request('module') === $module)>{{ str_replace('_', ' ', ucfirst($module)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="action" class="form-label small mb-1">Action</label>
                    <select id="action" name="action" class="form-select">
                        <option value="">All actions</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" @selected(request('action') === $action)>{{ str_replace('_', ' ', ucfirst($action)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="user" class="form-label small mb-1">User</label>
                    <select id="user" name="user" class="form-select">
                        <option value="">All users</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((string) request('user') === (string) $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-1">
                    <label for="date_from" class="form-label small mb-1">From</label>
                    <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}" class="form-control">
                </div>
                <div class="col-sm-6 col-lg-1">
                    <label for="date_to" class="form-label small mb-1">To</label>
                    <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}" class="form-control">
                </div>
                <div class="col-lg-1 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-grow-1" title="Filter"><i class="bi bi-search"></i></button>
                    @if (request()->hasAny(['search', 'module', 'action', 'user', 'date_from', 'date_to']))
                        <a href="{{ route('activity-logs.index') }}" class="btn btn-light" title="Clear filters"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Module</th>
                    <th>Description</th>
                    <th class="d-none d-lg-table-cell">IP Address</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-nowrap small">{{ $log->created_at?->toAppDateTime() }}</td>
                        <td class="small">{{ $log->user?->name ?? 'Guest / System' }}</td>
                        <td><span class="badge text-bg-{{ $actionColor($log->action) }}-subtle text-{{ $actionColor($log->action) }}-emphasis">{{ str_replace('_', ' ', $log->action) }}</span></td>
                        <td class="small text-body-secondary">{{ str_replace('_', ' ', $log->module) }}</td>
                        <td class="small">
                            {{ $log->description }}
                            @if ($log->properties)
                                <details class="mt-1">
                                    <summary class="text-body-secondary">Details</summary>
                                    <pre class="small bg-body-tertiary border rounded p-2 mb-0 mt-1 text-wrap">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            @endif
                        </td>
                        <td class="d-none d-lg-table-cell small text-body-secondary" title="{{ $log->user_agent }}">{{ $log->ip_address ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-body-secondary py-5"><i class="bi bi-journal-text d-block fs-2 mb-2"></i>No activity found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="small text-body-secondary"><i class="bi bi-shield-lock me-1"></i>Activity logs are read-only and cannot be edited or deleted.</span>
            @if ($logs->hasPages()){{ $logs->links() }}@endif
        </div>
    </div>
@endsection
