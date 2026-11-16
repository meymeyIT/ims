@extends('layouts.app')

@section('title', 'Stock History')

@section('breadcrumb')
    <li class="breadcrumb-item">Inventory</li>
    <li class="breadcrumb-item active" aria-current="page">Stock History</li>
@endsection

@section('actions')
    <a href="{{ route('stock-movements.export', request()->query()) }}" class="btn btn-outline-success"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Export</a>
    <a href="{{ route('stock-movements.print', request()->query()) }}" target="_blank" class="btn btn-light"><i class="bi bi-printer me-1"></i> Print</a>
    @can('stock.adjust')
        <a href="{{ route('stock-adjustments.create') }}" class="btn btn-warning"><i class="bi bi-sliders me-1"></i> Adjustment</a>
    @endcan
@endsection

@php use App\Support\Quantity; @endphp

@section('content')
    <div class="card">
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('stock-movements.index') }}" class="row g-2 align-items-end">
                <div class="col-lg-3">
                    <label for="search" class="form-label small mb-1">Search</label>
                    <input type="search" id="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Product, SKU or transaction #">
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="type" class="form-label small mb-1">Type</label>
                    <select id="type" name="type" class="form-select">
                        <option value="">All types</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
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
                    @if (request()->hasAny(['search', 'type', 'user', 'date_from', 'date_to']))
                        <a href="{{ route('stock-movements.index') }}" class="btn btn-light" title="Clear filters"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Transaction</th>
                    <th>Product</th>
                    <th>Type</th>
                    <th class="text-end">Quantity</th>
                    <th class="text-end d-none d-md-table-cell">Previous</th>
                    <th class="text-end d-none d-md-table-cell">New</th>
                    <th class="d-none d-xl-table-cell">Reference / Reason</th>
                    <th class="d-none d-lg-table-cell">User</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($movements as $movement)
                    @php $purchase = $movement->reference?->receipt?->purchase; @endphp
                    <tr class="{{ $movement->reversal ? 'opacity-75' : '' }}">
                        <td class="text-nowrap small">
                            <a href="{{ route('stock-movements.show', $movement) }}" class="fw-medium">{{ $movement->movement_number }}</a>
                            <div class="text-body-secondary">{{ $movement->movement_date->toAppDateTime() }}</div>
                        </td>
                        <td>
                            <div class="fw-medium">{{ $movement->product->name }} @if ($movement->product->trashed())<span class="badge text-bg-dark">Archived</span>@endif</div>
                            <div class="small text-body-secondary">{{ $movement->product->sku }}</div>
                        </td>
                        <td class="text-nowrap">
                            <x-movement-type :movement="$movement" />
                            @if ($movement->reversal)<div class="small text-body-secondary">reversed</div>@endif
                        </td>
                        <td class="text-end"><x-signed-quantity :quantity="$movement->quantity" :unit="$movement->product->unit->abbreviation" /></td>
                        <td class="text-end d-none d-md-table-cell">{{ Quantity::format($movement->previous_stock) }}</td>
                        <td class="text-end d-none d-md-table-cell fw-semibold">{{ Quantity::format($movement->new_stock) }}</td>
                        <td class="d-none d-xl-table-cell small">
                            @if ($purchase)
                                <a href="{{ route('purchases.show', $purchase) }}">{{ $purchase->purchase_number }}</a>
                            @else
                                {{ str_replace('_', ' ', ucfirst((string) $movement->reason)) ?: '—' }}
                            @endif
                        </td>
                        <td class="d-none d-lg-table-cell small">{{ $movement->user->name }}</td>
                        <td class="text-end"><a href="{{ route('stock-movements.show', $movement) }}" class="btn btn-sm btn-light" title="Details"><i class="bi bi-eye"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-body-secondary py-5"><i class="bi bi-clock-history d-block fs-2 mb-2"></i>No stock movements found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($movements->hasPages())
            <div class="card-footer bg-white">{{ $movements->links() }}</div>
        @endif
    </div>
@endsection
