@extends('layouts.app')

@php use App\Reports\ReportRegistry; @endphp

@section('title', $group ? ReportRegistry::GROUPS[$group][0] : 'Reports')

@section('breadcrumb')
    @if ($group)
        <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ ReportRegistry::GROUPS[$group][0] }}</li>
    @else
        <li class="breadcrumb-item active" aria-current="page">Reports</li>
    @endif
@endsection

@section('content')
    @forelse ($grouped as $groupKey => $reports)
        <h2 class="h6 text-uppercase text-body-secondary fw-semibold mb-3 {{ $loop->first ? '' : 'mt-4' }}">
            <i class="bi bi-{{ ReportRegistry::GROUPS[$groupKey][1] }} me-1"></i> {{ ReportRegistry::GROUPS[$groupKey][0] }}
        </h2>
        <div class="row g-3">
            @foreach ($reports as $key => $report)
                <div class="col-md-6 col-xl-4">
                    <a href="{{ route('reports.show', $key) }}" class="card h-100 text-decoration-none report-card">
                        <div class="card-body d-flex gap-3">
                            <span class="stat-icon bg-primary-subtle text-primary-emphasis"><i class="bi bi-file-earmark-bar-graph"></i></span>
                            <div>
                                <div class="fw-semibold text-body">{{ $report->title() }}</div>
                                <div class="small text-body-secondary">{{ $report->description() }}</div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @empty
        <div class="card"><div class="card-body text-center text-body-secondary py-5">No reports available for your role.</div></div>
    @endforelse
@endsection
