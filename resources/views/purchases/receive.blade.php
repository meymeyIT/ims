@extends('layouts.app')

@section('title', "Receive {$purchase->purchase_number}")

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('purchases.index') }}">Purchases</a></li>
    <li class="breadcrumb-item"><a href="{{ route('purchases.show', $purchase) }}">{{ $purchase->purchase_number }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">Receive</li>
@endsection

@php use App\Support\Quantity; @endphp

@section('content')
    <div class="alert alert-info small">
        <i class="bi bi-info-circle me-1"></i>
        Enter what actually arrived from <strong>{{ $purchase->supplier->name }}</strong>. You can receive part of the order now and the rest later.
        Leave a line at 0 if nothing arrived for it. Stock is updated as soon as you confirm.
    </div>

    @foreach (['items', 'receipt_token', 'purchase'] as $key)
        @error($key)<div class="alert alert-danger"><i class="bi bi-x-circle me-1"></i> {{ $message }}</div>@enderror
    @endforeach

    <form method="POST" action="{{ route('purchases.receive.store', $purchase) }}" novalidate
          data-confirm="Receive these quantities into stock? This cannot be undone (only reversed)." data-confirm-button="Receive" data-confirm-color="#198754">
        @csrf
        <input type="hidden" name="receipt_token" value="{{ old('receipt_token', $token) }}">

        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0">Products</h2>
                <button type="button" class="btn btn-sm btn-outline-success" data-fill-remaining><i class="bi bi-check2-all me-1"></i> Receive all remaining</button>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>Product</th>
                        <th class="text-end">Ordered</th>
                        <th class="text-end">Already received</th>
                        <th class="text-end">Remaining</th>
                        <th style="width: 200px">Receiving now</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($purchase->items as $item)
                        @php $remaining = $item->remainingQuantity(); $unit = $item->product->unit->abbreviation; @endphp
                        <tr class="{{ $item->isFullyReceived() ? 'table-light text-body-secondary' : '' }}">
                            <td>
                                <div class="fw-medium">{{ $item->product->name }}</div>
                                <div class="small text-body-secondary">{{ $item->product->sku }}</div>
                            </td>
                            <td class="text-end">{{ Quantity::format($item->quantity_ordered) }} {{ $unit }}</td>
                            <td class="text-end">{{ Quantity::format($item->quantity_received) }}</td>
                            <td class="text-end fw-semibold">{{ Quantity::format($remaining) }}</td>
                            <td>
                                @if ($item->isFullyReceived())
                                    <span class="badge text-bg-success-subtle text-success-emphasis"><i class="bi bi-check2"></i> Complete</span>
                                @else
                                    <div class="input-group input-group-sm has-validation">
                                        <input type="number" name="items[{{ $item->id }}]" step="0.001" min="0" max="{{ $remaining }}"
                                               value="{{ old("items.{$item->id}", '0') }}" data-remaining="{{ Quantity::format($remaining) }}"
                                               class="form-control text-end @error("items.{$item->id}") is-invalid @enderror">
                                        <span class="input-group-text">{{ $unit }}</span>
                                        @error("items.{$item->id}")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label for="received_at" class="form-label">Received date & time <span class="text-danger">*</span></label>
                    <input id="received_at" type="datetime-local" name="received_at" max="{{ now()->format('Y-m-d\TH:i') }}"
                           value="{{ old('received_at', now()->format('Y-m-d\TH:i')) }}"
                           class="form-control @error('received_at') is-invalid @enderror" required>
                    @error('received_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-8">
                    <label for="notes" class="form-label">Notes</label>
                    <input id="notes" type="text" name="notes" value="{{ old('notes') }}" maxlength="1000"
                           class="form-control" placeholder="Delivery note number, condition of goods…">
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2">
                <a href="{{ route('purchases.show', $purchase) }}" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-success"><i class="bi bi-box-arrow-in-down me-1"></i> Confirm Receipt</button>
            </div>
        </div>
    </form>
@endsection
