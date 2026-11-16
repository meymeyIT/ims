@php
    use App\Support\Currency;
    use App\Support\Quantity;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $purchase->purchase_number }} · {{ $business['name'] }}</title>
    @vite(['resources/css/app.css'])
    <style>
        body { background: #fff; font-size: 13px; }
        .sheet { max-width: 820px; margin: 2rem auto; padding: 0 1rem; }
        .doc-title { font-size: 1.75rem; font-weight: 700; letter-spacing: .02em; }
        .table > :not(caption) > * > * { padding: .5rem .6rem; }
        @media print {
            .no-print { display: none !important; }
            .sheet { margin: 0; max-width: none; padding: 0; }
            @page { margin: 14mm; }
        }
    </style>
</head>
<body>
<div class="sheet">
    <div class="no-print d-flex justify-content-end gap-2 mb-4">
        <a href="{{ route('purchases.show', $purchase) }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print</button>
    </div>

    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            @if ($logo = \App\Support\Branding::logoUrl())<img src="{{ $logo }}" alt="" style="max-height:56px;max-width:200px" class="mb-2 d-block">@endif
            <div class="fs-4 fw-bold">{{ $business['name'] }}</div>
            @if ($business['address'])<div>{!! nl2br(e($business['address'])) !!}</div>@endif
            @if ($business['phone'])<div>Tel: {{ $business['phone'] }}</div>@endif
            @if ($business['email'])<div>{{ $business['email'] }}</div>@endif
        </div>
        <div class="text-end">
            <div class="doc-title text-primary">PURCHASE ORDER</div>
            <div><strong>{{ $purchase->purchase_number }}</strong></div>
            <div>Date: {{ $purchase->purchase_date->toAppDate() }}</div>
            <div>Status: {{ $purchase->status->label() }}</div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-6">
            <div class="text-uppercase small text-body-secondary fw-semibold mb-1">Supplier</div>
            <div class="fw-semibold">{{ $purchase->supplier->name }}</div>
            @if ($purchase->supplier->company_name)<div>{{ $purchase->supplier->company_name }}</div>@endif
            @if ($purchase->supplier->address)<div>{!! nl2br(e($purchase->supplier->address)) !!}</div>@endif
            @if ($purchase->supplier->phone)<div>Tel: {{ $purchase->supplier->phone }}</div>@endif
            @if ($purchase->supplier->tax_number)<div>Tax no.: {{ $purchase->supplier->tax_number }}</div>@endif
        </div>
        <div class="col-6 text-end">
            <div class="text-uppercase small text-body-secondary fw-semibold mb-1">Prepared by</div>
            <div>{{ $purchase->creator->name }}</div>
            @if ($purchase->approver)
                <div class="mt-2 text-uppercase small text-body-secondary fw-semibold">Approved by</div>
                <div>{{ $purchase->approver->name }} · {{ $purchase->approved_at->toAppDate() }}</div>
            @endif
        </div>
    </div>

    <table class="table table-bordered">
        <thead class="table-light">
        <tr><th style="width:40px">#</th><th>Product</th><th>SKU</th><th class="text-end">Qty</th><th class="text-end">Unit cost</th><th class="text-end">Amount</th></tr>
        </thead>
        <tbody>
        @foreach ($purchase->items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->product->name }}</td>
                <td>{{ $item->product->sku }}</td>
                <td class="text-end text-nowrap">{{ Quantity::format($item->quantity_ordered) }} {{ $item->product->unit->abbreviation }}</td>
                <td class="text-end">{{ Currency::format($item->unit_cost) }}</td>
                <td class="text-end">{{ Currency::format($item->subtotal) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr><td colspan="5" class="text-end">Subtotal</td><td class="text-end">{{ Currency::format($purchase->subtotal) }}</td></tr>
        <tr><td colspan="5" class="text-end">Discount</td><td class="text-end">− {{ Currency::format($purchase->discount) }}</td></tr>
        <tr><td colspan="5" class="text-end">Tax</td><td class="text-end">+ {{ Currency::format($purchase->tax) }}</td></tr>
        <tr class="fw-bold"><td colspan="5" class="text-end">TOTAL</td><td class="text-end fs-6">{{ Currency::format($purchase->total_amount) }}</td></tr>
        </tfoot>
    </table>

    @if ($purchase->notes)
        <div class="mb-4"><strong>Notes:</strong><br>{!! nl2br(e($purchase->notes)) !!}</div>
    @endif

    <div class="row mt-5 pt-4 text-center">
        <div class="col-4"><div class="border-top pt-2">Prepared by</div></div>
        <div class="col-4"><div class="border-top pt-2">Approved by</div></div>
        <div class="col-4"><div class="border-top pt-2">Supplier</div></div>
    </div>

    <div class="text-center small text-body-secondary mt-4">Printed {{ now()->toAppDateTime() }}</div>
</div>
</body>
</html>
