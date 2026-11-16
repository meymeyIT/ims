@extends('layouts.app')

@section('title', 'Low Stock Alerts')

@section('breadcrumb')
    <li class="breadcrumb-item">Inventory</li>
    <li class="breadcrumb-item active" aria-current="page">Low Stock Alerts</li>
@endsection

@php
    use App\Support\Quantity;
    $orderable = $lowStock->filter(fn ($p) => $p->suggestedRestockQuantity() !== null);
@endphp

@section('actions')
    @if ($orderable->isNotEmpty())
        @can('purchases.create')
            <a href="{{ route('purchases.create', ['products' => $orderable->pluck('id')->all()]) }}" class="btn btn-primary">
                <i class="bi bi-cart-plus me-1"></i> Create purchase for all ({{ $orderable->count() }})
            </a>
        @endcan
    @endif
@endsection

@section('content')
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0">
                <i class="bi bi-exclamation-triangle text-warning me-1"></i> Products at or below minimum stock
                <span class="badge text-bg-light border ms-1">{{ $lowStock->count() }}</span>
            </h2>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Product</th>
                    <th class="d-none d-md-table-cell">Category</th>
                    <th class="text-end">Current</th>
                    <th class="text-end">Minimum</th>
                    <th class="text-end">Suggested order</th>
                    <th class="text-end"></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($lowStock as $product)
                    @php $suggested = $product->suggestedRestockQuantity(); $out = bccomp((string) $product->current_stock, '0', 3) <= 0; @endphp
                    <tr>
                        <td>
                            <a href="{{ route('products.show', $product) }}" class="fw-medium text-body text-decoration-none">{{ $product->name }}</a>
                            <div class="small text-body-secondary">{{ $product->sku }}</div>
                        </td>
                        <td class="d-none d-md-table-cell">{{ $product->category->name }}</td>
                        <td class="text-end text-nowrap">
                            @if ($out)
                                <span class="badge text-bg-danger-subtle text-danger-emphasis"><i class="bi bi-x-octagon"></i> Out of stock</span>
                            @else
                                <span class="fw-semibold text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> {{ Quantity::format($product->current_stock) }}</span>
                            @endif
                            <span class="small text-body-secondary">{{ $product->unit->abbreviation }}</span>
                        </td>
                        <td class="text-end">{{ Quantity::format($product->minimum_stock) }}</td>
                        <td class="text-end fw-semibold">{{ $suggested ? Quantity::format($suggested) . ' ' . $product->unit->abbreviation : '—' }}</td>
                        <td class="text-end text-nowrap">
                            @can('purchases.create')
                                @if ($suggested)
                                    <a href="{{ route('purchases.create', ['products' => [$product->id]]) }}" class="btn btn-sm btn-outline-primary" title="Create a purchase order">
                                        <i class="bi bi-cart-plus"></i> Order
                                    </a>
                                @endif
                            @endcan
                            @can('stock.in')
                                <a href="{{ route('stock-in.create', ['product' => $product->id]) }}" class="btn btn-sm btn-light" title="Stock in"><i class="bi bi-box-arrow-in-down"></i></a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-body-secondary py-5">
                            <i class="bi bi-check2-circle text-success d-block fs-2 mb-2"></i>All products are above their minimum stock.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($lowStock->isNotEmpty())
            <div class="card-footer bg-white small text-body-secondary">
                Suggested order = enough to bring the stock up to <strong>twice the minimum</strong>.
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-header bg-white d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <h2 class="h6 mb-0">Notification history</h2>
            <div class="d-flex gap-2 align-items-center">
                <div class="btn-group btn-group-sm" role="group" aria-label="Filter notifications">
                    <a href="{{ route('notifications.index') }}" class="btn btn-outline-secondary {{ request('filter') !== 'unread' ? 'active' : '' }}">All</a>
                    <a href="{{ route('notifications.index', ['filter' => 'unread']) }}" class="btn btn-outline-secondary {{ request('filter') === 'unread' ? 'active' : '' }}">
                        Unread @if ($unreadCount)<span class="badge text-bg-danger ms-1">{{ $unreadCount }}</span>@endif
                    </a>
                </div>
                @if ($unreadCount > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-light"><i class="bi bi-check2-all me-1"></i> Mark all read</button>
                    </form>
                @endif
            </div>
        </div>
        <ul class="list-group list-group-flush">
            @forelse ($notifications as $notification)
                <li class="list-group-item d-flex gap-3 align-items-start {{ $notification->read_at ? '' : 'bg-primary-subtle bg-opacity-25' }}">
                    <span class="stat-icon bg-warning-subtle text-warning-emphasis" style="width:2.25rem;height:2.25rem;font-size:1rem"><i class="bi bi-exclamation-triangle"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <a href="{{ route('notifications.read', $notification) }}" class="text-body text-decoration-none {{ $notification->read_at ? '' : 'fw-semibold' }}">
                            {{ $notification->data['message'] ?? 'Notification' }}
                        </a>
                        <div class="small text-body-secondary">
                            {{ $notification->created_at->toAppDateTime() }} · {{ $notification->created_at->diffForHumans() }}
                            @if ($notification->read_at) · read {{ $notification->read_at->diffForHumans() }} @endif
                        </div>
                    </div>
                    @unless ($notification->read_at)<span class="badge text-bg-primary">New</span>@endunless
                </li>
            @empty
                <li class="list-group-item text-center text-body-secondary py-5">No notifications.</li>
            @endforelse
        </ul>
        @if ($notifications->hasPages())
            <div class="card-footer bg-white">{{ $notifications->links() }}</div>
        @endif
    </div>
@endsection
