<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title() }}</title>
    {{-- DomPDF supports only simple CSS, so this view has its own small stylesheet. --}}
    <style>
        @page { margin: 14mm 10mm 16mm 10mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5px; color: #1f2937; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        .meta { color: #6b7280; margin-bottom: 10px; }
        .business { font-size: 11px; font-weight: bold; }
        table.report-doc { width: 100%; border-collapse: collapse; }
        .report-doc th { background: #eef2f7; text-align: left; font-weight: bold; }
        .report-doc th, .report-doc td { border: 0.5px solid #d1d5db; padding: 3px 4px; vertical-align: top; }
        .report-doc .num { text-align: right; white-space: nowrap; }
        .report-doc tfoot td { font-weight: bold; background: #f9fafb; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .warning { background: #fff7e6; border: 0.5px solid #f0c36d; padding: 4px 6px; margin-bottom: 8px; }
        .note { color: #4b5563; margin-top: 8px; }
        .footer { position: fixed; bottom: -10mm; left: 0; right: 0; color: #9ca3af; font-size: 7.5px; }
        .footer .page:after { content: counter(page); }
    </style>
</head>
<body>
<div class="footer">{{ $businessName }} · {{ $report->title() }} · Generated {{ now()->toAppDateTime() }} · Page <span class="page"></span></div>

@if ($logoPath = \App\Support\Branding::logoPath())
    {{-- DomPDF reads the logo from disk, not over HTTP. Needs the gd extension. --}}
    <img src="{{ $logoPath }}" alt="" style="max-height: 36px; max-width: 160px; margin-bottom: 4px;">
@endif
<div class="business">{{ $businessName }}</div>
<h1>{{ $report->title() }}</h1>
<div class="meta">{{ $filterLine }}</div>

@if ($truncated)
    <div class="warning">Only the first {{ number_format($limit) }} rows are included. Use the Excel export for the complete data.</div>
@endif

@include('reports._document-table', ['plain' => true])

</body>
</html>
