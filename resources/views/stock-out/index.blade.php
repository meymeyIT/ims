@extends('layouts.app')

@section('title', 'Stock Out')

@section('breadcrumb')
    <li class="breadcrumb-item">Inventory</li>
    <li class="breadcrumb-item active" aria-current="page">Stock Out</li>
@endsection

@section('actions')
    <a href="{{ route('stock-out.create') }}" class="btn btn-primary"><i class="bi bi-box-arrow-up me-1"></i> New Stock Out</a>
@endsection

@php
    use App\Enums\StockOutReason;
    use App\Support\Quantity;
@endphp

@section('content')
    <div class="card">
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('stock-out.index') }}" class="row g-2 align-items-end">
                <div class="col-lg-4">
                    <label for="search" class="form-label small mb-1">Product</label>
                    <input type="search" id="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Name, SKU or barcode">
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="reason" class="form-label small mb-1">Reason</label>
                    <select id="reason" name="reason" class="form-select">
                        <option value="">All reasons</option>
                        @foreach ($reasons as $reason)
                            <option value="{{ $reason->value }}" @selected(request('reason') === $reason->value)>{{ $reason->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="date_from" class="form-label small mb-1">From</label>
                    <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}" class="form-control">
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="date_to" class="form-label small mb-1">To</label>
                    <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}" class="form-control">
                </div>
                <div class="col-sm-6 col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="bi bi-search"></i> Filter</button>
                    @if (request()->hasAny(['search', 'reason', 'date_from', 'date_to']))
                        <a href="{{ route('stock-out.index') }}" class="btn btn-light" title="Clear filters"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Product</th>
                    <th class="text-end">Quantity</th>
                    <th class="text-end d-none d-lg-table-cell">Stock</th>
                    <th>Reason</th>
                    <th class="d-none d-md-table-cell">User</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($movements as $movement)
                    <tr>
                        <td class="text-nowrap small">
                            {{ $movement->movement_date->toAppDateTime() }}
                            <div><a href="{{ route('stock-movements.show', $movement) }}" class="text-body-secondary">{{ $movement->movement_number }}</a></div>
                        </td>
                        <td>
                            <a href="{{ route('products.show', $movement->product) }}" class="text-body text-decoration-none fw-medium">{{ $movement->product->name }}</a>
                            <div class="small text-body-secondary">{{ $movement->product->sku }}</div>
                        </td>
                        <td class="text-end"><x-signed-quantity :quantity="$movement->quantity" :unit="$movement->product->unit->abbreviation" /></td>
                        <td class="text-end d-none d-lg-table-cell small text-nowrap">{{ Quantity::format($movement->previous_stock) }} → <strong>{{ Quantity::format($movement->new_stock) }}</strong></td>
                        <td class="small">
                            @if ($movement->reversalOf)
                                <span class="badge text-bg-warning-subtle text-warning-emphasis">Reversal of {{ $movement->reversalOf->movement_number }}</span>
                            @else
                                {{ StockOutReason::tryFrom((string) $movement->reason)?->label() ?? $movement->reason }}
                                @if ($movement->supplier)<div class="text-body-secondary">to {{ $movement->supplier->name }}</div>@endif
                            @endif
                        </td>
                        <td class="d-none d-md-table-cell small">{{ $movement->user->name }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-body-secondary py-5">
                            <i class="bi bi-box-arrow-up d-block fs-2 mb-2"></i>No stock out yet.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($movements->hasPages())
            <div class="card-footer bg-white">{{ $movements->links() }}</div>
        @endif
    </div>
@endsection
