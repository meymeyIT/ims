@extends('layouts.app')

@section('title', 'Stock In')

@section('breadcrumb')
    <li class="breadcrumb-item">Inventory</li>
    <li class="breadcrumb-item active" aria-current="page">Stock In</li>
@endsection

@section('actions')
    <a href="{{ route('stock-in.create') }}" class="btn btn-primary"><i class="bi bi-box-arrow-in-down me-1"></i> New Stock In</a>
@endsection

@php
    use App\Support\Currency;
    use App\Support\Quantity;
@endphp

@section('content')
    <div class="card">
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('stock-in.index') }}" class="row g-2 align-items-end">
                <div class="col-lg-3">
                    <label for="search" class="form-label small mb-1">Product</label>
                    <input type="search" id="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Name, SKU or barcode">
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="source" class="form-label small mb-1">Source</label>
                    <select id="source" name="source" class="form-select">
                        <option value="">All</option>
                        <option value="purchase" @selected(request('source') === 'purchase')>Purchase receipts</option>
                        <option value="manual" @selected(request('source') === 'manual')>Manual stock in</option>
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="supplier" class="form-label small mb-1">Supplier</label>
                    <select id="supplier" name="supplier" class="form-select">
                        <option value="">All</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) request('supplier') === (string) $supplier->id)>{{ $supplier->name }}</option>
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
                <div class="col-lg-1 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-grow-1" title="Filter"><i class="bi bi-search"></i></button>
                    @if (request()->hasAny(['search', 'source', 'supplier', 'date_from', 'date_to']))
                        <a href="{{ route('stock-in.index') }}" class="btn btn-light" title="Clear filters"><i class="bi bi-x-lg"></i></a>
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
                    <th class="text-end d-none d-md-table-cell">Unit cost</th>
                    <th class="text-end d-none d-lg-table-cell">Stock</th>
                    <th>Source</th>
                    <th class="d-none d-lg-table-cell">User</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($movements as $movement)
                    @php $purchase = $movement->reference?->receipt?->purchase; @endphp
                    <tr>
                        <td class="text-nowrap small">
                            {{ $movement->movement_date->toAppDateTime() }}
                            <div><a href="{{ route('stock-movements.show', $movement) }}" class="text-body-secondary">{{ $movement->movement_number }}</a></div>
                        </td>
                        <td>
                            <a href="{{ route('products.show', $movement->product) }}" class="text-body text-decoration-none fw-medium">{{ $movement->product->name }}</a>
                            <div class="small text-body-secondary">{{ $movement->product->sku }}</div>
                        </td>
                        <td class="text-end fw-semibold text-nowrap {{ $movement->isIncrease() ? 'text-success' : 'text-danger' }}">{{ $movement->isIncrease() ? '+' : '' }}{{ Quantity::format($movement->quantity) }} <span class="small text-body-secondary fw-normal">{{ $movement->product->unit->abbreviation }}</span></td>
                        <td class="text-end d-none d-md-table-cell">{{ $movement->unit_cost !== null ? Currency::format($movement->unit_cost) : '—' }}</td>
                        <td class="text-end d-none d-lg-table-cell small text-nowrap">{{ Quantity::format($movement->previous_stock) }} → <strong>{{ Quantity::format($movement->new_stock) }}</strong></td>
                        <td class="small">
                            @if ($movement->reversalOf)
                                <span class="badge text-bg-warning-subtle text-warning-emphasis">Reversal of {{ $movement->reversalOf->movement_number }}</span>
                            @elseif ($purchase)
                                <a href="{{ route('purchases.show', $purchase) }}"><i class="bi bi-receipt me-1"></i>{{ $purchase->purchase_number }}</a>
                            @else
                                {{ $reasons[$movement->reason] ?? ucfirst(str_replace('_', ' ', (string) $movement->reason)) }}
                            @endif
                            @if ($movement->supplier)<div class="text-body-secondary">{{ $movement->supplier->name }}</div>@endif
                        </td>
                        <td class="d-none d-lg-table-cell small">{{ $movement->user->name }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-body-secondary py-5">
                            <i class="bi bi-box-arrow-in-down d-block fs-2 mb-2"></i>No stock in yet.
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
