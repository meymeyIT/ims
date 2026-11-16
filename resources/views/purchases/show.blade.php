@extends('layouts.app')

@section('title', $purchase->purchase_number)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('purchases.index') }}">Purchases</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $purchase->purchase_number }}</li>
@endsection

@php
    use App\Enums\PurchaseStatus;
    use App\Support\Currency;
    use App\Support\Quantity;
    $status = $purchase->status;
@endphp

@section('actions')
    <a href="{{ route('purchases.print', $purchase) }}" target="_blank" class="btn btn-light"><i class="bi bi-printer me-1"></i> Print</a>

    @if ($status->isEditable())
        @can('purchases.create')
            <a href="{{ route('purchases.edit', $purchase) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i> Edit</a>
            <form method="POST" action="{{ route('purchases.submit', $purchase) }}" class="d-inline">
                @csrf @method('PATCH')
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-send me-1"></i> Submit</button>
            </form>
        @endcan
    @endif

    @if ($status->canBeApproved())
        @can('purchases.approve')
            <form method="POST" action="{{ route('purchases.approve', $purchase) }}" class="d-inline"
                  data-confirm="Approve {{ $purchase->purchase_number }} for {{ Currency::format($purchase->total_amount) }}?" data-confirm-button="Approve" data-confirm-color="#0d6efd">
                @csrf @method('PATCH')
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Approve</button>
            </form>
        @endcan
    @endif

    @if ($status->canBeReceived())
        @can('purchases.receive')
            <a href="{{ route('purchases.receive', $purchase) }}" class="btn btn-success"><i class="bi bi-box-arrow-in-down me-1"></i> Receive Products</a>
        @endcan
    @endif

    @if ($status->canBeCancelled())
        @can('purchases.cancel')
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal"><i class="bi bi-x-circle me-1"></i> Cancel</button>
        @endcan
    @endif

    @if ($status->isEditable())
        @can('purchases.create')
            <x-delete-button :action="route('purchases.destroy', $purchase)" button-class="btn btn-outline-danger" label="Delete"
                             :message="'Delete draft ' . $purchase->purchase_number . '? This cannot be undone.'" />
        @endcan
    @endif
@endsection

@section('content')
    @foreach (['purchase', 'reason', 'items'] as $key)
        @error($key)<div class="alert alert-danger"><i class="bi bi-x-circle me-1"></i> {{ $message }}</div>@enderror
    @endforeach

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0">Products</h2>
                    <span class="badge fs-6 text-bg-{{ $status->badge() }}-subtle text-{{ $status->badge() }}-emphasis">{{ $status->label() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Ordered</th>
                            <th class="text-end">Received</th>
                            <th class="text-end">Remaining</th>
                            <th class="text-end">Unit cost</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($purchase->items as $item)
                            @php $unit = $item->product->unit->abbreviation; $remaining = $item->remainingQuantity(); @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('products.show', $item->product) }}" class="text-decoration-none text-body fw-medium">{{ $item->product->name }}</a>
                                    <div class="small text-body-secondary">{{ $item->product->sku }}</div>
                                </td>
                                <td class="text-end text-nowrap">{{ Quantity::format($item->quantity_ordered) }} {{ $unit }}</td>
                                <td class="text-end text-nowrap {{ bccomp($item->quantity_received, '0', 3) > 0 ? 'text-success' : '' }}">{{ Quantity::format($item->quantity_received) }}</td>
                                <td class="text-end text-nowrap">
                                    @if ($item->isFullyReceived())
                                        <i class="bi bi-check-circle-fill text-success" title="Fully received"></i>
                                    @else
                                        {{ Quantity::format($remaining) }}
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">{{ Currency::format($item->unit_cost) }}</td>
                                <td class="text-end text-nowrap">{{ Currency::format($item->subtotal) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="table-group-divider">
                        <tr><td colspan="5" class="text-end">Subtotal</td><td class="text-end">{{ Currency::format($purchase->subtotal) }}</td></tr>
                        <tr><td colspan="5" class="text-end">Discount</td><td class="text-end">− {{ Currency::format($purchase->discount) }}</td></tr>
                        <tr><td colspan="5" class="text-end">Tax</td><td class="text-end">+ {{ Currency::format($purchase->tax) }}</td></tr>
                        <tr class="fw-bold"><td colspan="5" class="text-end border-0">Total</td><td class="text-end border-0 fs-5">{{ Currency::format($purchase->total_amount) }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white"><h2 class="h6 mb-0">Receiving history</h2></div>
                @forelse ($purchase->receipts as $receipt)
                    <div class="card-body {{ $loop->last ? '' : 'border-bottom' }}">
                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                            <div>
                                <span class="fw-semibold">{{ $receipt->receipt_number }}</span>
                                <span class="text-body-secondary small">· {{ $receipt->received_at->toAppDateTime() }} · by {{ $receipt->receiver->name }}</span>
                            </div>
                        </div>
                        <ul class="list-unstyled small mb-0">
                            @foreach ($receipt->items as $receiptItem)
                                <li><i class="bi bi-box-arrow-in-down text-success me-1"></i>{{ $receiptItem->product->name }}: <strong>+{{ Quantity::format($receiptItem->quantity) }} {{ $receiptItem->product->unit->abbreviation }}</strong></li>
                            @endforeach
                        </ul>
                        @if ($receipt->notes)<div class="small text-body-secondary mt-2"><i class="bi bi-chat-left-text me-1"></i>{{ $receipt->notes }}</div>@endif
                    </div>
                @empty
                    <div class="card-body text-center text-body-secondary py-4">
                        Nothing received yet.
                        @if (in_array($status, [PurchaseStatus::Draft, PurchaseStatus::Pending], true))
                            <div class="small">The purchase must be approved before goods can be received.</div>
                        @endif
                    </div>
                @endforelse
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-header bg-white"><h2 class="h6 mb-0">Supplier</h2></div>
                <div class="card-body">
                    <a href="{{ route('suppliers.show', $purchase->supplier) }}" class="fw-semibold text-decoration-none">{{ $purchase->supplier->name }}</a>
                    @if ($purchase->supplier->company_name)<div class="small">{{ $purchase->supplier->company_name }}</div>@endif
                    @if ($purchase->supplier->phone)<div class="small"><i class="bi bi-telephone me-1"></i>{{ $purchase->supplier->phone }}</div>@endif
                    @if ($purchase->supplier->email)<div class="small"><i class="bi bi-envelope me-1"></i>{{ $purchase->supplier->email }}</div>@endif
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-white"><h2 class="h6 mb-0">Timeline</h2></div>
                <div class="card-body small">
                    <div class="mb-2"><i class="bi bi-calendar3 me-2 text-body-secondary"></i>Purchase date: <strong>{{ $purchase->purchase_date->toAppDate() }}</strong></div>
                    <div class="mb-2"><i class="bi bi-person me-2 text-body-secondary"></i>Created by {{ $purchase->creator->name }} · {{ $purchase->created_at->toAppDateTime() }}</div>
                    @if ($purchase->approver)
                        <div class="mb-2 text-primary"><i class="bi bi-check2-circle me-2"></i>Approved by {{ $purchase->approver->name }} · {{ $purchase->approved_at->toAppDateTime() }}</div>
                    @endif
                    @if ($purchase->canceller)
                        <div class="text-danger"><i class="bi bi-x-circle me-2"></i>Cancelled by {{ $purchase->canceller->name }} · {{ $purchase->cancelled_at->toAppDateTime() }}
                            <div class="ms-4">Reason: {{ $purchase->cancellation_reason }}</div>
                        </div>
                    @endif
                </div>
            </div>

            @if ($purchase->notes)
                <div class="card">
                    <div class="card-header bg-white"><h2 class="h6 mb-0">Notes</h2></div>
                    <div class="card-body small">{!! nl2br(e($purchase->notes)) !!}</div>
                </div>
            @endif
        </div>
    </div>

    @if ($status->canBeCancelled())
        @can('purchases.cancel')
            <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('purchases.cancel', $purchase) }}" class="modal-content">
                        @csrf @method('PATCH')
                        <div class="modal-header">
                            <h2 class="modal-title h5" id="cancelModalLabel">Cancel {{ $purchase->purchase_number }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-body-secondary">A cancelled purchase can no longer be received or edited.</p>
                            <label for="reason" class="form-label">Reason <span class="text-danger">*</span></label>
                            <textarea id="reason" name="reason" rows="3" maxlength="255" class="form-control" required></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Keep purchase</button>
                            <button type="submit" class="btn btn-danger">Cancel purchase</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    @endif
@endsection
