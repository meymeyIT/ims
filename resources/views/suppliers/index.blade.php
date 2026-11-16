@extends('layouts.app')

@section('title', 'Suppliers')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Suppliers</li>
@endsection

@section('actions')
    @can('suppliers.manage')
        <a href="{{ route('suppliers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add Supplier</a>
    @endcan
@endsection

@section('content')
    <div class="card">
        <div class="card-body border-bottom">
            <x-list-filters :action="route('suppliers.index')" placeholder="Name, company, contact, phone or email" />
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Supplier</th>
                    <th class="d-none d-md-table-cell">Contact</th>
                    <th class="d-none d-lg-table-cell">Phone / Email</th>
                    <th class="text-center">Purchases</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($suppliers as $supplier)
                    <tr>
                        <td>
                            <a href="{{ route('suppliers.show', $supplier) }}" class="fw-medium text-body text-decoration-none">{{ $supplier->name }}</a>
                            @if ($supplier->company_name)
                                <div class="small text-body-secondary">{{ $supplier->company_name }}</div>
                            @endif
                        </td>
                        <td class="d-none d-md-table-cell">{{ $supplier->contact_person ?: '—' }}</td>
                        <td class="d-none d-lg-table-cell small">
                            @if ($supplier->phone)<div><i class="bi bi-telephone me-1"></i>{{ $supplier->phone }}</div>@endif
                            @if ($supplier->email)<div><i class="bi bi-envelope me-1"></i>{{ $supplier->email }}</div>@endif
                            @if (! $supplier->phone && ! $supplier->email)—@endif
                        </td>
                        <td class="text-center">{{ $supplier->purchases_count }}</td>
                        <td><x-status-badge :active="$supplier->is_active" /></td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-sm btn-light" title="View"><i class="bi bi-eye"></i></a>
                            @can('suppliers.manage')
                                <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-sm btn-light" title="Edit"><i class="bi bi-pencil"></i></a>
                                <x-toggle-status-button :action="route('suppliers.toggle-status', $supplier)" :active="$supplier->is_active" :name="$supplier->name" />
                                @if ($supplier->purchases_count === 0)
                                    <x-delete-button :action="route('suppliers.destroy', $supplier)" :message="'Delete supplier “' . $supplier->name . '”?'" />
                                @else
                                    <button type="button" class="btn btn-sm btn-light text-body-tertiary" disabled title="Has purchase history: deactivate instead"><i class="bi bi-trash"></i></button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-body-secondary py-5">No suppliers found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($suppliers->hasPages())
            <div class="card-footer bg-white">{{ $suppliers->links() }}</div>
        @endif
    </div>
@endsection
