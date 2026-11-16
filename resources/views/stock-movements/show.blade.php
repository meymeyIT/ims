@extends('layouts.app')

@section('title', $movement->movement_number)

@section('breadcrumb')
    <li class="breadcrumb-item">Inventory</li>
    <li class="breadcrumb-item"><a href="{{ route('stock-movements.index') }}">Stock History</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $movement->movement_number }}</li>
@endsection

@php
    use App\Support\Currency;
    use App\Support\Quantity;
    $unit = $movement->product->unit->abbreviation;
    $purchase = $movement->reference?->receipt?->purchase;
    $canReverse = ! $movement->reversal_of_id && ! $movement->reversal;
@endphp

@section('actions')
    @if ($canReverse)
        @can('stock.reverse')
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reverseModal">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Reverse
            </button>
        @endcan
    @endif
@endsection

@section('content')
    @error('reason')<div class="alert alert-danger"><i class="bi bi-x-circle me-1"></i> {{ $message }}</div>@enderror

    @if ($movement->reversal)
        <div class="alert alert-warning">
            <i class="bi bi-arrow-counterclockwise me-1"></i>
            This movement was reversed by <a href="{{ route('stock-movements.show', $movement->reversal) }}" class="alert-link">{{ $movement->reversal->movement_number }}</a>
            on {{ $movement->reversal->movement_date->toAppDateTime() }} by {{ $movement->reversal->user->name }}.
        </div>
    @endif
    @if ($movement->reversalOf)
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-1"></i>
            This is a correction that reverses <a href="{{ route('stock-movements.show', $movement->reversalOf) }}" class="alert-link">{{ $movement->reversalOf->movement_number }}</a>.
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0">Transaction details</h2>
                    <x-movement-type :movement="$movement" />
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4 text-body-secondary fw-normal">Transaction #</dt><dd class="col-sm-8 fw-medium">{{ $movement->movement_number }}</dd>
                        <dt class="col-sm-4 text-body-secondary fw-normal">Date</dt><dd class="col-sm-8">{{ $movement->movement_date->toAppDateTime() }}</dd>
                        <dt class="col-sm-4 text-body-secondary fw-normal">Product</dt>
                        <dd class="col-sm-8"><a href="{{ route('products.show', $movement->product) }}">{{ $movement->product->name }}</a> <code class="ms-1">{{ $movement->product->sku }}</code></dd>
                        <dt class="col-sm-4 text-body-secondary fw-normal">Quantity</dt><dd class="col-sm-8"><x-signed-quantity :quantity="$movement->quantity" :unit="$unit" /></dd>
                        <dt class="col-sm-4 text-body-secondary fw-normal">Stock</dt>
                        <dd class="col-sm-8">{{ Quantity::format($movement->previous_stock) }} → <strong>{{ Quantity::format($movement->new_stock) }}</strong> {{ $unit }}</dd>
                        <dt class="col-sm-4 text-body-secondary fw-normal">Unit cost</dt><dd class="col-sm-8">{{ $movement->unit_cost !== null ? Currency::format($movement->unit_cost) : '—' }}</dd>
                        <dt class="col-sm-4 text-body-secondary fw-normal">Reason</dt><dd class="col-sm-8">{{ str_replace('_', ' ', ucfirst((string) $movement->reason)) ?: '—' }}</dd>
                        <dt class="col-sm-4 text-body-secondary fw-normal">Reference</dt>
                        <dd class="col-sm-8">
                            @if ($purchase)
                                <a href="{{ route('purchases.show', $purchase) }}">{{ $purchase->purchase_number }}</a>
                                <span class="text-body-secondary">({{ $movement->reference->receipt->receipt_number }})</span>
                            @else
                                —
                            @endif
                        </dd>
                        <dt class="col-sm-4 text-body-secondary fw-normal">Supplier</dt><dd class="col-sm-8">{{ $movement->supplier?->name ?? '—' }}</dd>
                        <dt class="col-sm-4 text-body-secondary fw-normal">Recorded by</dt><dd class="col-sm-8">{{ $movement->user->name }} · {{ $movement->created_at->toAppDateTime() }}</dd>
                        <dt class="col-sm-4 text-body-secondary fw-normal">Notes</dt><dd class="col-sm-8 mb-0">{!! nl2br(e($movement->notes ?: '—')) !!}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-body small text-body-secondary">
                    <i class="bi bi-shield-lock me-1"></i>
                    Stock movements can't be edited or deleted. To correct a mistake, use <strong>Reverse</strong>.
                    It records an opposite movement and keeps both in the history.
                    @if ($movement->reference_type === 'purchase_receipt_item')
                        <div class="mt-2">Reversing a purchase receipt also reduces the received quantity on the purchase order.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($canReverse)
        @can('stock.reverse')
            <div class="modal fade" id="reverseModal" tabindex="-1" aria-labelledby="reverseModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('stock-movements.reverse', $movement) }}" class="modal-content">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title h5" id="reverseModalLabel">Reverse {{ $movement->movement_number }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="small">
                                This records <strong><x-signed-quantity :quantity="bcmul((string) $movement->quantity, '-1', 3)" :unit="$unit" /></strong>
                                for {{ $movement->product->name }}. The original movement stays in the history.
                            </p>
                            <label for="reason" class="form-label">Reason for the reversal <span class="text-danger">*</span></label>
                            <textarea id="reason" name="reason" rows="3" maxlength="255" minlength="3" class="form-control" required
                                      placeholder="e.g. Wrong quantity entered, should have been 10">{{ old('reason') }}</textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Reverse movement</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    @endif
@endsection
