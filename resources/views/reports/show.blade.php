@extends('layouts.app')

@php
    use App\Reports\Report;
    use App\Reports\ReportRegistry;
    $columns = $report->columns();
    $query = $filters->toQuery();
@endphp

@section('title', $report->title())

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
    <li class="breadcrumb-item"><a href="{{ route('reports.index', ['group' => $report->group()]) }}">{{ ReportRegistry::GROUPS[$report->group()][0] }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $report->title() }}</li>
@endsection

@section('actions')
    @can('reports.export')
        <a href="{{ route('reports.excel', [$report->key(), ...$query]) }}" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i> Excel</a>
        <a href="{{ route('reports.pdf', [$report->key(), ...$query]) }}" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</a>
        <a href="{{ route('reports.print', [$report->key(), ...$query]) }}" target="_blank" class="btn btn-light"><i class="bi bi-printer me-1"></i> Print</a>
    @endcan
@endsection

@section('content')
    <p class="text-body-secondary small mb-3">{{ $report->description() }}</p>

    <div class="card">
        <div class="card-body border-bottom">
            @include('reports._filters')
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0 report-table">
                <thead class="table-light">
                <tr>
                    @foreach ($columns as $column)
                        <th class="{{ Report::isNumeric($column['type']) ? 'text-end' : '' }}">{{ $column['label'] }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @forelse ($records as $record)
                    @php $row = $report->row($record); @endphp
                    <tr>
                        @foreach ($columns as $key => $column)
                            @php $value = $row[$key] ?? null; $numeric = Report::isNumeric($column['type']); @endphp
                            <td class="{{ $numeric ? 'text-end text-nowrap' : '' }} {{ $numeric && is_numeric($value) && $value < 0 ? 'text-danger' : '' }}">
                                {{ Report::format($value, $column['type']) }}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columns) }}" class="text-center text-body-secondary py-5"><i class="bi bi-inbox d-block fs-2 mb-2"></i>No records for these filters.</td></tr>
                @endforelse
                </tbody>
                @if ($totals && $records->isNotEmpty())
                    <tfoot class="table-group-divider fw-semibold">
                    <tr>
                        @foreach (array_keys($columns) as $i => $key)
                            <td class="{{ Report::isNumeric($columns[$key]['type']) ? 'text-end text-nowrap' : '' }}">
                                @if ($i === 0)
                                    {{ $report->totalsLabel() }}
                                @elseif (array_key_exists($key, $totals))
                                    {{ is_numeric($totals[$key]) ? Report::format($totals[$key], $columns[$key]['type']) : $totals[$key] }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="small text-body-secondary">
                {{ number_format($records->total()) }} record(s) · {{ $filters->describe() }}
                @if ($report->note())<div class="mt-1"><i class="bi bi-info-circle me-1"></i>{{ $report->note() }}</div>@endif
            </div>
            @if ($records->hasPages()){{ $records->links() }}@endif
        </div>
    </div>
@endsection
