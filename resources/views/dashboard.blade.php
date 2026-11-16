@extends('layouts.app')

@section('title', 'Dashboard')

@php
    use App\Support\Currency;
    use App\Support\Quantity;

    $user = auth()->user();
    $canPurchases = $user->can('purchases.view');

    // [label, value, icon, colour, sub-line, link route, link permission]
    $tiles = [
        ['Total Products', number_format($cards['products']), 'box-seam', 'primary', null, 'products.index', 'products.view'],
        ['Total Categories', number_format($cards['categories']), 'tags', 'info', null, 'categories.index', 'categories.view'],
        ['Total Suppliers', number_format($cards['suppliers']), 'truck', 'secondary', null, 'suppliers.index', 'suppliers.view'],
        ['Total Stock Quantity', Quantity::format($cards['total_stock']), 'stack', 'primary', 'units, all products', 'products.index', 'products.view'],
        ['Stock In Today', Quantity::format($cards['stock_in_today']), 'box-arrow-in-down', 'success', $cards['stock_in_today_count'] . ' transaction(s)', 'stock-in.index', 'stock.in'],
        ['Stock Out Today', Quantity::format($cards['stock_out_today']), 'box-arrow-up', 'danger', $cards['stock_out_today_count'] . ' transaction(s)', 'stock-out.index', 'stock.out'],
        ['Low Stock Products', number_format($cards['low_stock']), 'exclamation-triangle', $cards['low_stock'] > 0 ? 'warning' : 'success', $cards['low_stock'] > 0 ? 'need restocking' : 'all good', 'notifications.index', 'notifications.view'],
        ['Total Inventory Value', Currency::format($cards['inventory_value']), 'cash-stack', 'success', Currency::alternate($cards['inventory_value']), null, null],
    ];
    if ($canPurchases) {
        $tiles[] = ['Pending Purchases', number_format($cards['pending_purchases']), 'hourglass-split', 'warning', $cards['awaiting_delivery'] . ' awaiting delivery', 'purchases.index', 'purchases.view'];
        $tiles[] = ['Purchases This Month', Currency::format($cards['purchases_this_month']), 'receipt', 'primary', $cards['purchases_this_month_count'] . ' order(s)', 'purchases.index', 'purchases.view'];
    }

    $hasFlow = collect($charts['stockFlow']['in'])->sum() + collect($charts['stockFlow']['out'])->sum() > 0;
    $hasPurchases = collect($charts['purchases']['totals'])->sum() > 0;
@endphp

@section('content')
    {{-- ------------------------------------------------------------ cards --}}
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 row-cols-xxl-5 g-3 mb-4">
        @foreach ($tiles as [$label, $value, $icon, $color, $sub, $route, $permission])
            @php $link = $route && Route::has($route) && (! $permission || $user->can($permission)) ? route($route) : null; @endphp
            <div class="col">
                <div class="card stat-card h-100 {{ $link ? 'stat-card-link' : '' }}">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="stat-icon bg-{{ $color }}-subtle text-{{ $color }}-emphasis"><i class="bi bi-{{ $icon }}"></i></span>
                        <div class="min-w-0">
                            <div class="stat-label">
                                @if ($link)<a href="{{ $link }}" class="stretched-link text-reset text-decoration-none">{{ $label }}</a>@else{{ $label }}@endif
                            </div>
                            <div class="stat-value text-truncate" title="{{ $value }}">{{ $value }}</div>
                            @if ($sub)<div class="small text-body-secondary text-truncate">{{ $sub }}</div>@endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ------------------------------------------------------------ charts --}}
    <div class="row g-4 mb-4" id="dashboard-charts">
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h2 class="h6 mb-0">Monthly Stock In and Stock Out</h2>
                    <div class="small text-body-secondary">Quantities (all units), last 12 months</div>
                </div>
                <div class="card-body">
                    @if ($hasFlow)
                        <div class="chart-box"><canvas id="chart-stock-flow" role="img" aria-label="Bar chart of monthly stock in and stock out quantities"></canvas></div>
                        <x-chart-table :labels="$charts['stockFlow']['labels']" :series="['Stock in' => $charts['stockFlow']['in'], 'Stock out' => $charts['stockFlow']['out']]" />
                    @else
                        <div class="chart-empty">No stock movements in the last 12 months yet.</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h2 class="h6 mb-0">Low Stock Products</h2>
                    <div class="small text-body-secondary">Current stock as % of minimum</div>
                </div>
                <div class="card-body">
                    @if (count($charts['lowStock']['labels']))
                        <div class="chart-box"><canvas id="chart-low-stock" role="img" aria-label="Bar chart of low stock products as a percentage of their minimum stock"></canvas></div>
                        <x-chart-table :labels="$charts['lowStock']['labels']" :series="['% of minimum' => $charts['lowStock']['percent'], 'Stock / minimum' => $charts['lowStock']['detail']]" />
                    @else
                        <div class="chart-empty"><i class="bi bi-check2-circle text-success fs-3 d-block mb-1"></i>All products are above their minimum stock.</div>
                    @endif
                </div>
            </div>
        </div>

        @if ($canPurchases)
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header bg-white">
                        <h2 class="h6 mb-0">Monthly Purchase Trends</h2>
                        <div class="small text-body-secondary">Purchase order totals ({{ Currency::code() }}), excluding drafts and cancelled</div>
                    </div>
                    <div class="card-body">
                        @if ($hasPurchases)
                            <div class="chart-box"><canvas id="chart-purchases" role="img" aria-label="Line chart of monthly purchase totals"></canvas></div>
                            <x-chart-table :labels="$charts['purchases']['labels']" :series="['Total' => $charts['purchases']['totals'], 'Orders' => $charts['purchases']['orders']]" />
                        @else
                            <div class="chart-empty">No purchases in the last 12 months yet.</div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="{{ $canPurchases ? 'col-xl-6' : 'col-xl-6' }}">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h2 class="h6 mb-0">Product Distribution by Category</h2>
                    <div class="small text-body-secondary">Active products per category</div>
                </div>
                <div class="card-body">
                    @if (count($charts['categories']['labels']))
                        <div class="chart-box"><canvas id="chart-categories" role="img" aria-label="Bar chart of active products per category"></canvas></div>
                        <x-chart-table :labels="$charts['categories']['labels']" :series="['Products' => $charts['categories']['counts']]" />
                    @else
                        <div class="chart-empty">No products yet.</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="{{ $canPurchases ? 'col-12' : 'col-xl-6' }}">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h2 class="h6 mb-0">Top 10 Most Frequently Used Products</h2>
                    <div class="small text-body-secondary">Number of stock-out transactions, last 90 days</div>
                </div>
                <div class="card-body">
                    @if (count($charts['topProducts']['labels']))
                        <div class="chart-box"><canvas id="chart-top-products" role="img" aria-label="Bar chart of the most frequently issued products"></canvas></div>
                        <x-chart-table :labels="$charts['topProducts']['labels']" :series="['Transactions' => $charts['topProducts']['transactions'], 'Quantity' => $charts['topProducts']['quantities']]" />
                    @else
                        <div class="chart-empty">No stock out in the last 90 days yet.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="dashboard-data">@json($charts)</script>

    {{-- ------------------------------------------------------------ widgets --}}
    <div class="row g-4">
        @if ($user->can('stock.view'))
            <div class="col-xl-7">
                <div class="card h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h2 class="h6 mb-0">Recent Stock Movements</h2>
                        <a href="{{ route('stock-movements.index') }}" class="small">View all</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <tbody>
                            @forelse ($recentMovements as $movement)
                                <tr>
                                    <td class="small text-nowrap text-body-secondary">{{ $movement->movement_date->format('d/m H:i') }}</td>
                                    <td><a href="{{ route('stock-movements.show', $movement) }}" class="text-body text-decoration-none">{{ $movement->product->name }}</a></td>
                                    <td class="text-nowrap"><x-movement-type :movement="$movement" /></td>
                                    <td class="text-end"><x-signed-quantity :quantity="$movement->quantity" :unit="$movement->product->unit->abbreviation" /></td>
                                    <td class="small text-body-secondary d-none d-md-table-cell">{{ $movement->user->name }}</td>
                                </tr>
                            @empty
                                <tr><td class="text-center text-body-secondary py-4">No stock movements yet.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0">Low Stock Alerts</h2>
                    @if ($user->can('notifications.view'))<a href="{{ route('notifications.index') }}" class="small">View all</a>@endif
                </div>
                <ul class="list-group list-group-flush">
                    @forelse ($lowStockProducts as $product)
                        @php $out = bccomp((string) $product->current_stock, '0', 3) <= 0; @endphp
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div class="min-w-0">
                                <a href="{{ route('products.show', $product) }}" class="text-body text-decoration-none fw-medium">{{ $product->name }}</a>
                                <div class="small text-body-secondary">{{ $product->sku }}</div>
                            </div>
                            <div class="text-end text-nowrap">
                                @if ($out)
                                    <span class="badge text-bg-danger-subtle text-danger-emphasis"><i class="bi bi-x-octagon"></i> Out of stock</span>
                                @else
                                    <span class="badge text-bg-warning-subtle text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> Low</span>
                                @endif
                                <div class="small">{{ Quantity::format($product->current_stock) }} / {{ Quantity::format($product->minimum_stock) }} {{ $product->unit->abbreviation }}</div>
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item text-center text-body-secondary py-4"><i class="bi bi-check2-circle text-success"></i> No low-stock products.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        @if ($canPurchases)
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h2 class="h6 mb-0">Recent Purchases</h2>
                        <a href="{{ route('purchases.index') }}" class="small">View all</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <tbody>
                            @forelse ($recentPurchases as $purchase)
                                <tr>
                                    <td><a href="{{ route('purchases.show', $purchase) }}" class="fw-medium text-decoration-none">{{ $purchase->purchase_number }}</a>
                                        <div class="small text-body-secondary">{{ $purchase->supplier->name }}</div></td>
                                    <td class="small text-nowrap">{{ $purchase->purchase_date->toAppDate() }}</td>
                                    <td><span class="badge text-bg-{{ $purchase->status->badge() }}-subtle text-{{ $purchase->status->badge() }}-emphasis">{{ $purchase->status->label() }}</span></td>
                                    <td class="text-end text-nowrap">{{ Currency::format($purchase->total_amount) }}</td>
                                </tr>
                            @empty
                                <tr><td class="text-center text-body-secondary py-4">No purchases yet.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @if ($user->can('activity_logs.view'))
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h2 class="h6 mb-0">Recent Activities</h2>
                        @if (Route::has('activity-logs.index'))<a href="{{ route('activity-logs.index') }}" class="small">View all</a>@endif
                    </div>
                    <ul class="list-group list-group-flush">
                        @forelse ($recentActivities as $activity)
                            <li class="list-group-item">
                                <div class="small">{{ $activity->description }}</div>
                                <div class="small text-body-secondary">{{ $activity->user?->name ?? 'System' }} · {{ $activity->created_at?->diffForHumans() }}</div>
                            </li>
                        @empty
                            <li class="list-group-item text-center text-body-secondary py-4">No activity yet.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        @endif
    </div>
@endsection
