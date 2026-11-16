@php
    $user = auth()->user();
    $unreadCount = $user->unreadNotifications()->count();
    $latestUnread = $unreadCount > 0 ? $user->unreadNotifications()->latest()->limit(5)->get() : collect();
@endphp

<header class="app-topbar">
    <button class="btn btn-light d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Open menu">
        <i class="bi bi-list fs-5"></i>
    </button>

    <div class="ms-auto d-flex align-items-center gap-2">
        {{-- Notification bell --}}
        <div class="dropdown">
            <button class="btn btn-light position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                <i class="bi bi-bell fs-5"></i>
                @if ($unreadCount > 0)
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                        <span class="visually-hidden">unread notifications</span>
                    </span>
                @endif
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow-sm notification-menu p-0">
                <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Notifications</span>
                    @if ($unreadCount > 0 && Route::has('notifications.read-all'))
                        <form method="POST" action="{{ route('notifications.read-all') }}">
                            @csrf
                            <button type="submit" class="btn btn-link btn-sm p-0 text-decoration-none">Mark all read</button>
                        </form>
                    @endif
                </div>
                @forelse ($latestUnread as $notification)
                    <a class="dropdown-item py-2 text-wrap" href="{{ Route::has('notifications.read') ? route('notifications.read', $notification) : ($notification->data['url'] ?? '#') }}">
                        <div class="small">{{ $notification->data['message'] ?? 'New notification' }}</div>
                        <div class="text-body-secondary" style="font-size: .75rem">{{ $notification->created_at->diffForHumans() }}</div>
                    </a>
                @empty
                    <div class="px-3 py-4 text-center small text-body-secondary">
                        <i class="bi bi-check2-circle d-block fs-4 mb-1"></i>No new notifications
                    </div>
                @endforelse
                @if (Route::has('notifications.index') && $user->can('notifications.view'))
                    <a class="dropdown-item text-center small border-top py-2" href="{{ route('notifications.index') }}">View all notifications</a>
                @endif
            </div>
        </div>

        {{-- User menu --}}
        <div class="dropdown">
            <button class="btn btn-light d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="avatar">{{ $user->initials() }}</span>
                <span class="d-none d-sm-block text-start lh-sm">
                    <span class="d-block small fw-semibold">{{ $user->name }}</span>
                    <span class="d-block text-body-secondary" style="font-size: .75rem">{{ $user->role?->display_name }}</span>
                </span>
                <i class="bi bi-chevron-down small"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>My Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Log out</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
