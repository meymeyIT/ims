@extends('layouts.app')

@section('title', 'Purchases')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Purchases</li>
@endsection

@section('actions')
    @can('purchases.create')
        <a href="{{ route('purchases.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Create Purchase</a>
    @endcan
@endsection

@php use App\Support\Currency; @endphp

@section('content')
    <div class="card">
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('purchases.index') }}" class="row g-2 align-items-end">
                <div class="col-lg-3">
                    <label for="search" class="form-label small mb-1">Search</label>
                    <input type="search" id="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="PO number or supplier">
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="status" class="form-label small mb-1">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="supplier" class="form-label small mb-1">Supplier</label>
                    <select id="supplier" name="supplier" class="form-select">
                        <option value="">All suppliers</option>
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
                    @if (request()->hasAny(['search', 'status', 'supplier', 'date_from', 'date_to']))
                        <a href="{{ route('purchases.index') }}" class="btn btn-light" title="Clear filters"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Purchase #</th>
                    <th>Supplier</th>
                    <th>Date</th>
                    <th class="text-center d-none d-md-table-cell">Items</th>
                    <th class="text-end">Total</th>
                    <th>Status</th>
                    <th class="d-none d-lg-table-cell">Created by</th>
                    <th class="text-end"></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($purchases as $purchase)
                    <tr>
                        <td><a href="{{ route('purchases.show', $purchase) }}" class="fw-medium text-decoration-none">{{ $purchase->purchase_number }}</a></td>
                        <td>{{ $purchase->supplier->name }}</td>
                        <td class="text-nowrap">{{ $purchase->purchase_date->toAppDate() }}</td>
                        <td class="text-center d-none d-md-table-cell">{{ $purchase->items_count }}</td>
                        <td class="text-end text-nowrap">{{ Currency::format($purchase->total_amount) }}</td>
                        <td><span class="badge text-bg-{{ $purchase->status->badge() }}-subtle text-{{ $purchase->status->badge() }}-emphasis">{{ $purchase->status->label() }}</span></td>
                        <td class="d-none d-lg-table-cell small text-body-secondary">{{ $purchase->creator->name }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('purchases.show', $purchase) }}" class="btn btn-sm btn-light" title="View"><i class="bi bi-eye"></i></a>
                            @if ($purchase->status->canBeReceived())
                                @can('purchases.receive')
                                    <a href="{{ route('purchases.receive', $purchase) }}" class="btn btn-sm btn-light text-success" title="Receive"><i class="bi bi-box-arrow-in-down"></i></a>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-body-secondary py-5">
                            <i class="bi bi-receipt d-block fs-2 mb-2"></i>No purchases found.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($purchases->hasPages())
            <div class="card-footer bg-white">{{ $purchases->links() }}</div>
        @endif
    </div>
@endsection
