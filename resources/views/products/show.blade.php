@extends('layouts.app')

@section('title', $product->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $product->sku }}</li>
@endsection

@php
    use App\Support\Currency;
    use App\Support\Quantity;

    $stockBadges = [
        'in_stock' => ['In stock', 'success'],
        'low_stock' => ['Low stock', 'warning'],
        'out_of_stock' => ['Out of stock', 'danger'],
    ];
    [$stockText, $stockColor] = $stockBadges[$product->stockStatus()];
    $unit = $product->unit->abbreviation;
@endphp

@section('actions')
    @if (! $product->trashed() && $product->is_active)
        @can('stock.in')
            <a href="{{ route('stock-in.create', ['product' => $product->id]) }}" class="btn btn-outline-success"><i class="bi bi-box-arrow-in-down me-1"></i> Stock In</a>
        @endcan
        @can('stock.out')
            <a href="{{ route('stock-out.create', ['product' => $product->id]) }}" class="btn btn-outline-danger"><i class="bi bi-box-arrow-up me-1"></i> Stock Out</a>
        @endcan
    @endif
    @can('stock.adjust')
        <a href="{{ route('stock-adjustments.create', ['product' => $product->id]) }}" class="btn btn-outline-warning"><i class="bi bi-sliders me-1"></i> Adjust</a>
    @endcan
    @can('products.update')
        <a href="{{ route('products.edit', $product) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i> Edit</a>
        <x-toggle-status-button :action="route('products.toggle-status', $product)" :active="$product->is_active" :name="$product->name" />
    @endcan
    @can('products.delete')
        <x-delete-button :action="route('products.destroy', $product)" title="Archive" icon="archive" confirm="Archive" label="Archive" button-class="btn btn-outline-danger"
                         :message="'Archive “' . $product->name . '”? It will be hidden but its history is kept.'" />
    @endcan
@endsection

@section('content')
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <div class="image-preview mb-3">
                        @if ($product->imageUrl())
                            <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}">
                        @else
                            <div class="text-body-tertiary"><i class="bi bi-image fs-1 d-block"></i><span class="small">No image</span></div>
                        @endif
                    </div>
                    <div class="d-flex justify-content-center gap-2">
                        <x-status-badge :active="$product->is_active" />
                        <span class="badge text-bg-{{ $stockColor }}-subtle text-{{ $stockColor }}-emphasis">{{ $stockText }}</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="stat-label">Current stock</div>
                    <div class="display-6 fw-bold">{{ Quantity::format($product->current_stock) }} <span class="fs-5 text-body-secondary">{{ $unit }}</span></div>
                    <div class="small text-body-secondary mb-3">Minimum: {{ Quantity::format($product->minimum_stock) }} {{ $unit }}</div>
                    <div class="d-flex justify-content-between small"><span>Average cost</span><strong>{{ Currency::format($product->average_cost) }}</strong></div>
                    <div class="d-flex justify-content-between small"><span>Stock value</span><strong>{{ Currency::format($product->stockValue()) }}</strong></div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header bg-white"><h2 class="h6 mb-0">Details</h2></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4 text-body-secondary fw-normal">SKU</dt><dd class="col-sm-8"><code>{{ $product->sku }}</code></dd>
                        <dt class="col-sm-4 text-body-secondary fw-normal">Barcode</dt><dd class="col-sm-8">{{ $product->barcode ?: '—' }}</dd>
                        <dt class="col-sm-4 text-body-secondary fw-normal">Category</dt><dd class="col-sm-8">{{ $product->category->name }}</dd>
                        <dt class="col-sm-4 text-body-secondary fw-normal">Unit</dt><dd class="col-sm-8">{{ $product->unit->name }} ({{ $unit }})</dd>
                        <dt class="col-sm-4 text-body-secondary fw-normal">Purchase price</dt><dd class="col-sm-8">{{ Currency::format($product->purchase_price) }}</dd>
                        <dt class="col-sm-4 text-body-secondary fw-normal">Selling price</dt><dd class="col-sm-8">{{ Currency::format($product->selling_price) }}</dd>
                        <dt class="col-sm-4 text-body-secondary fw-normal">Description</dt><dd class="col-sm-8">{!! nl2br(e($product->description ?: '—')) !!}</dd>
                        <dt class="col-sm-4 text-body-secondary fw-normal">Created</dt><dd class="col-sm-8">{{ $product->created_at?->toAppDateTime() }}</dd>
                        <dt class="col-sm-4 text-body-secondary fw-normal">Last updated</dt><dd class="col-sm-8 mb-0">{{ $product->updated_at?->toAppDateTime() }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0">Recent stock movements</h2>
                    @can('stock.view')
                        <a href="{{ route('stock-movements.index', ['search' => $product->sku]) }}" class="small">Full history</a>
                    @endcan
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                        <tr><th>Date</th><th>Type</th><th class="text-end">Qty</th><th class="text-end">Stock after</th><th class="d-none d-md-table-cell">User</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($movements as $movement)
                            <tr>
                                <td class="small">@can('stock.view')<a href="{{ route('stock-movements.show', $movement) }}" class="text-body">{{ $movement->movement_date->toAppDateTime() }}</a>@else{{ $movement->movement_date->toAppDateTime() }}@endcan</td>
                                <td><x-movement-type :movement="$movement" /></td>
                                <td class="text-end {{ $movement->isIncrease() ? 'text-success' : 'text-danger' }}">{{ $movement->isIncrease() ? '+' : '' }}{{ Quantity::format($movement->quantity) }}</td>
                                <td class="text-end">{{ Quantity::format($movement->new_stock) }}</td>
                                <td class="d-none d-md-table-cell small">{{ $movement->user?->name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-body-secondary py-4">No stock movements yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white"><h2 class="h6 mb-0">Purchase history</h2></div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                        <tr><th>Purchase</th><th class="d-none d-md-table-cell">Supplier</th><th class="text-end">Ordered</th><th class="text-end">Received</th><th class="text-end">Unit cost</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($purchaseItems as $item)
                            <tr>
                                <td>
                                    {{ $item->purchase->purchase_number }}
                                    <span class="badge text-bg-{{ $item->purchase->status->badge() }}-subtle text-{{ $item->purchase->status->badge() }}-emphasis">{{ $item->purchase->status->label() }}</span>
                                </td>
                                <td class="d-none d-md-table-cell">{{ $item->purchase->supplier?->name }}</td>
                                <td class="text-end">{{ Quantity::format($item->quantity_ordered) }}</td>
                                <td class="text-end">{{ Quantity::format($item->quantity_received) }}</td>
                                <td class="text-end">{{ Currency::format($item->unit_cost) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-body-secondary py-4">Not purchased yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
