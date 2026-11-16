@extends('layouts.app')

@section('title', $supplier->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('suppliers.index') }}">Suppliers</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $supplier->name }}</li>
@endsection

@php use App\Support\Currency; @endphp

@section('actions')
    @can('suppliers.manage')
        <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i> Edit</a>
        <x-toggle-status-button :action="route('suppliers.toggle-status', $supplier)" :active="$supplier->is_active" :name="$supplier->name" />
    @endcan
@endsection

@section('content')
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0">Supplier information</h2>
                    <x-status-badge :active="$supplier->is_active" />
                </div>
                <div class="card-body">
                    <dl class="mb-0 small">
                        <dt class="text-body-secondary fw-normal">Company</dt><dd>{{ $supplier->company_name ?: '—' }}</dd>
                        <dt class="text-body-secondary fw-normal">Contact person</dt><dd>{{ $supplier->contact_person ?: '—' }}</dd>
                        <dt class="text-body-secondary fw-normal">Phone</dt>
                        <dd>@if ($supplier->phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $supplier->phone) }}">{{ $supplier->phone }}</a>@else — @endif</dd>
                        <dt class="text-body-secondary fw-normal">Email</dt>
                        <dd>@if ($supplier->email)<a href="mailto:{{ $supplier->email }}">{{ $supplier->email }}</a>@else — @endif</dd>
                        <dt class="text-body-secondary fw-normal">Address</dt><dd>{!! nl2br(e($supplier->address ?: '—')) !!}</dd>
                        <dt class="text-body-secondary fw-normal">Tax number</dt><dd>{{ $supplier->tax_number ?: '—' }}</dd>
                        <dt class="text-body-secondary fw-normal">Notes</dt><dd class="mb-0">{!! nl2br(e($supplier->description ?: '—')) !!}</dd>
                    </dl>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-6">
                    <div class="card"><div class="card-body">
                        <div class="stat-label">Purchase orders</div>
                        <div class="stat-value">{{ number_format($totals->orders) }}</div>
                    </div></div>
                </div>
                <div class="col-6">
                    <div class="card"><div class="card-body">
                        <div class="stat-label">Total purchased</div>
                        <div class="stat-value text-truncate">{{ Currency::format($totals->amount) }}</div>
                    </div></div>
                </div>
            </div>
            <div class="form-text">Totals exclude cancelled orders.</div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white"><h2 class="h6 mb-0">Purchase history</h2></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr><th>Purchase #</th><th>Date</th><th>Status</th><th class="text-end">Total</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($purchases as $purchase)
                            <tr>
                                <td>
                                    @if (Route::has('purchases.show'))
                                        <a href="{{ route('purchases.show', $purchase) }}">{{ $purchase->purchase_number }}</a>
                                    @else
                                        {{ $purchase->purchase_number }}
                                    @endif
                                </td>
                                <td>{{ $purchase->purchase_date->toAppDate() }}</td>
                                <td><span class="badge text-bg-{{ $purchase->status->badge() }}-subtle text-{{ $purchase->status->badge() }}-emphasis">{{ $purchase->status->label() }}</span></td>
                                <td class="text-end">{{ Currency::format($purchase->total_amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-body-secondary py-5">No purchases from this supplier yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($purchases->hasPages())
                    <div class="card-footer bg-white">{{ $purchases->links() }}</div>
                @endif
            </div>

            @can('suppliers.manage')
                @unless ($supplier->hasHistory())
                    <div class="text-end mt-3">
                        <x-delete-button :action="route('suppliers.destroy', $supplier)" label="Delete supplier" button-class="btn btn-sm btn-outline-danger"
                                         :message="'Delete supplier “' . $supplier->name . '”? This cannot be undone.'" />
                    </div>
                @endunless
            @endcan
        </div>
    </div>
@endsection
