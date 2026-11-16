@php use App\Reports\Report; $columns = $report->columns(); @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report->title() }} · {{ $businessName }}</title>
    @vite(['resources/css/app.css'])
    <style>
        body { background: #fff; font-size: 12px; }
        .sheet { margin: 1.5rem; }
        .table > :not(caption) > * > * { padding: .3rem .45rem; }
        @media print {
            .no-print { display: none !important; }
            .sheet { margin: 0; }
            @page { size: A4 {{ $report->orientation() }}; margin: 10mm; }
            thead { display: table-header-group; }   /* repeat headings on every page */
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="sheet">
    <div class="no-print d-flex justify-content-end gap-2 mb-3">
        <a href="{{ route('reports.show', [$report->key(), ...$filters->toQuery()]) }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print</button>
    </div>

    <div class="d-flex justify-content-between align-items-end mb-3">
        <div>
            @if ($logo = \App\Support\Branding::logoUrl())<img src="{{ $logo }}" alt="" style="max-height:44px;max-width:180px" class="mb-1 d-block">@endif
            <div class="fs-5 fw-bold">{{ $businessName }}</div>
            <div class="fs-6">{{ $report->title() }}</div>
        </div>
        <div class="text-end small">
            {{ $filterLine }}<br>
            Printed {{ now()->toAppDateTime() }} by {{ auth()->user()->name }}
        </div>
    </div>

    @if ($truncated)
        <div class="alert alert-warning py-2 small">Only the first {{ number_format($limit) }} rows are shown. Narrow the filters or use the Excel export for everything.</div>
    @endif

    @include('reports._document-table')
</div>
</body>
</html>
