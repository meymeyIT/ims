@php use App\Support\Quantity; @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stock History · {{ \App\Models\Setting::get('business_name', config('app.name')) }}</title>
    @vite(['resources/css/app.css'])
    <style>
        body { background: #fff; font-size: 12px; }
        .sheet { margin: 1.5rem; }
        .table > :not(caption) > * > * { padding: .35rem .5rem; }
        @media print { .no-print { display: none !important; } .sheet { margin: 0; } @page { size: A4 landscape; margin: 10mm; } }
    </style>
</head>
<body>
<div class="sheet">
    <div class="no-print d-flex justify-content-end gap-2 mb-3">
        <a href="{{ route('stock-movements.index', request()->query()) }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print</button>
    </div>

    <div class="d-flex justify-content-between align-items-end mb-3">
        <div>
            <div class="fs-5 fw-bold">{{ \App\Models\Setting::get('business_name', config('app.name')) }}</div>
            <div class="fs-6">Stock Movement History</div>
        </div>
        <div class="text-end small">
            @if (request('date_from') || request('date_to'))
                Period: {{ request('date_from') ?: '…' }} to {{ request('date_to') ?: '…' }}<br>
            @endif
            @if (request('type'))Type: {{ strtoupper(request('type')) }}<br>@endif
            Printed {{ now()->toAppDateTime() }} by {{ auth()->user()->name }}
        </div>
    </div>

    @if ($truncated)
        <div class="alert alert-warning py-2 small">Only the latest {{ number_format($limit) }} rows are shown. Narrow the filters or use Export for the full list.</div>
    @endif

    <table class="table table-bordered table-sm">
        <thead class="table-light">
        <tr>
            <th>Transaction #</th><th>Date</th><th>Product</th><th>SKU</th><th>Type</th>
            <th class="text-end">Qty</th><th class="text-end">Previous</th><th class="text-end">New</th><th>Reason</th><th>User</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($movements as $m)
            <tr>
                <td>{{ $m->movement_number }}</td>
                <td class="text-nowrap">{{ $m->movement_date->toAppDateTime() }}</td>
                <td>{{ $m->product->name }}</td>
                <td>{{ $m->product->sku }}</td>
                <td>{{ $m->type->label() }}{{ $m->reversal_of_id ? ' (reversal)' : '' }}</td>
                <td class="text-end text-nowrap">{{ bccomp((string) $m->quantity, '0', 3) > 0 ? '+' : '' }}{{ Quantity::format($m->quantity) }} {{ $m->product->unit->abbreviation }}</td>
                <td class="text-end">{{ Quantity::format($m->previous_stock) }}</td>
                <td class="text-end">{{ Quantity::format($m->new_stock) }}</td>
                <td>{{ $m->reference?->receipt?->purchase?->purchase_number ?? str_replace('_', ' ', (string) $m->reason) }}</td>
                <td>{{ $m->user->name }}</td>
            </tr>
        @empty
            <tr><td colspan="10" class="text-center py-4">No stock movements for these filters.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="small text-body-secondary">{{ number_format($movements->count()) }} transaction(s)</div>
</div>
</body>
</html>
