@php
    /*
     | Sidebar menu. Each item: [label, icon, route name, permission, active route pattern(s)].
     | An item only appears when its route exists AND the user has the permission,
     | so menu entries for modules built in later phases appear automatically.
     | (Hiding links is only for convenience: every route is also protected on the server.)
     */
    $menu = [
        null => [
            ['Dashboard', 'speedometer2', 'dashboard', 'dashboard.view', 'dashboard'],
        ],
        'Products' => [
            ['Products', 'box-seam', 'products.index', 'products.view', 'products.*'],
            ['Categories', 'tags', 'categories.index', 'categories.view', 'categories.*'],
            ['Units', 'rulers', 'units.index', 'units.view', 'units.*'],
        ],
        'Suppliers' => [
            ['Suppliers', 'truck', 'suppliers.index', 'suppliers.view', 'suppliers.*'],
        ],
        'Purchases' => [
            ['All Purchases', 'receipt', 'purchases.index', 'purchases.view', 'purchases.index|purchases.show|purchases.edit|purchases.receive*'],
            ['Create Purchase', 'plus-circle', 'purchases.create', 'purchases.create', 'purchases.create'],
        ],
        'Inventory' => [
            ['Stock In', 'box-arrow-in-down', 'stock-in.index', 'stock.in', 'stock-in.*'],
            ['Stock Out', 'box-arrow-up', 'stock-out.index', 'stock.out', 'stock-out.*'],
            ['Stock History', 'clock-history', 'stock-movements.index', 'stock.view', 'stock-movements.*|stock-adjustments.*'],
            ['Low Stock Alerts', 'exclamation-triangle', 'notifications.index', 'notifications.view', 'notifications.*'],
        ],
        'Reports' => [
            // 6th element: route parameters, 7th: report group (for the active state)
            ['Product Reports', 'file-earmark-bar-graph', 'reports.index', 'reports.view', 'reports.*', ['group' => 'products'], 'products'],
            ['Stock Reports', 'bar-chart-line', 'reports.index', 'reports.view', 'reports.*', ['group' => 'stock'], 'stock'],
            ['Purchase Reports', 'graph-up', 'reports.index', ['reports.view', 'purchases.view'], 'reports.*', ['group' => 'purchases'], 'purchases'],
            ['Inventory Valuation', 'cash-stack', 'reports.show', 'reports.view', 'reports.*', ['report' => 'inventory-valuation'], 'valuation'],
        ],
        'System' => [
            ['Users', 'people', 'users.index', 'users.view', 'users.*'],
            ['Roles', 'shield-lock', 'roles.index', 'roles.view', 'roles.*'],
            ['Activity Logs', 'journal-text', 'activity-logs.index', 'activity_logs.view', 'activity-logs.*'],
            ['Settings', 'gear', 'settings.edit', 'settings.manage', 'settings.*'],
        ],
    ];

    $user = auth()->user();

    // Which report group is open right now (to highlight the right Reports link).
    $currentReportGroup = null;
    if (request()->routeIs('reports.*')) {
        $currentReportGroup = request()->query('group')
            ?? (request()->route('report') ? App\Reports\ReportRegistry::find((string) request()->route('report'))?->group() : null);
    }
@endphp

<aside class="app-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="appSidebar" aria-labelledby="appSidebarLabel">
    <div class="offcanvas-header border-bottom">
        <a href="{{ route('dashboard') }}" class="sidebar-brand" id="appSidebarLabel">
            @if ($businessLogo)<img src="{{ $businessLogo }}" alt="" class="brand-logo">@else<span class="brand-mark"><i class="bi bi-box-seam"></i></span>@endif
            <span class="text-truncate">{{ $businessName }}</span>
        </a>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="Close menu"></button>
    </div>

    <div class="offcanvas-body d-flex flex-column p-0">
        <a href="{{ route('dashboard') }}" class="sidebar-brand d-none d-lg-flex">
            @if ($businessLogo)<img src="{{ $businessLogo }}" alt="" class="brand-logo">@else<span class="brand-mark"><i class="bi bi-box-seam"></i></span>@endif
            <span class="text-truncate">{{ $businessName }}</span>
        </a>

        <nav class="sidebar-nav" aria-label="Main navigation">
            @foreach ($menu as $heading => $items)
                @php
                    // A permission can be a list: the user needs all of them.
                    $visible = array_filter($items, fn ($item) => Route::has($item[2])
                        && collect((array) $item[3])->every(fn ($permission) => $user->can($permission)));
                @endphp

                @if ($visible)
                    @if ($heading)
                        <div class="sidebar-heading">{{ $heading }}</div>
                    @endif

                    <ul class="nav flex-column">
                        @foreach ($visible as $item)
                            @php
                                [$label, $icon, $route, , $pattern] = $item;
                                $params = $item[5] ?? [];
                                $reportGroup = $item[6] ?? null;
                                $active = $reportGroup !== null
                                    ? $currentReportGroup === $reportGroup
                                    : request()->routeIs(...explode('|', $pattern));
                            @endphp
                            <li class="nav-item">
                                <a href="{{ route($route, $params) }}" class="nav-link {{ $active ? 'active' : '' }}" @if ($active) aria-current="page" @endif>
                                    <i class="bi bi-{{ $icon }}"></i>
                                    <span>{{ $label }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endforeach
        </nav>

        <div class="sidebar-footer">
            <div class="small text-body-secondary">Signed in as</div>
            <div class="fw-medium text-truncate">{{ $user->name }}</div>
            <span class="badge text-bg-primary-subtle text-primary-emphasis">{{ $user->role?->display_name }}</span>
        </div>
    </div>
</aside>
