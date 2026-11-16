@extends('layouts.app')

@section('title', 'Units')

@section('breadcrumb')
    <li class="breadcrumb-item">Products</li>
    <li class="breadcrumb-item active" aria-current="page">Units</li>
@endsection

@section('actions')
    @can('units.manage')
        <a href="{{ route('units.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add Unit</a>
    @endcan
@endsection

@section('content')
    <div class="card">
        <div class="card-body border-bottom">
            <x-list-filters :action="route('units.index')" placeholder="Unit name or abbreviation" />
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Abbreviation</th>
                    <th class="d-none d-md-table-cell">Description</th>
                    <th class="text-center">Products</th>
                    <th>Status</th>
                    @can('units.manage')<th class="text-end">Actions</th>@endcan
                </tr>
                </thead>
                <tbody>
                @forelse ($units as $unit)
                    <tr>
                        <td class="fw-medium">{{ $unit->name }}</td>
                        <td><code>{{ $unit->abbreviation }}</code></td>
                        <td class="d-none d-md-table-cell text-body-secondary small">{{ $unit->description ?: '—' }}</td>
                        <td class="text-center">{{ $unit->products_count }}</td>
                        <td><x-status-badge :active="$unit->is_active" /></td>
                        @can('units.manage')
                            <td class="text-end text-nowrap">
                                <a href="{{ route('units.edit', $unit) }}" class="btn btn-sm btn-light" title="Edit"><i class="bi bi-pencil"></i></a>
                                <x-toggle-status-button :action="route('units.toggle-status', $unit)" :active="$unit->is_active" :name="$unit->name" />
                                @if ($unit->products_count === 0)
                                    <x-delete-button :action="route('units.destroy', $unit)" :message="'Delete unit “' . $unit->name . '”?'" />
                                @else
                                    <button type="button" class="btn btn-sm btn-light text-body-tertiary" disabled title="Used by {{ $unit->products_count }} product(s): deactivate instead"><i class="bi bi-trash"></i></button>
                                @endif
                            </td>
                        @endcan
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-body-secondary py-5">No units found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($units->hasPages())
            <div class="card-footer bg-white">{{ $units->links() }}</div>
        @endif
    </div>
@endsection
