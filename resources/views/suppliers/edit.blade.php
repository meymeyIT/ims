@extends('layouts.app')

@section('title', 'Edit Supplier')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('suppliers.index') }}">Suppliers</a></li>
    <li class="breadcrumb-item"><a href="{{ route('suppliers.show', $supplier) }}">{{ $supplier->name }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit</li>
@endsection

@section('content')
    <div class="card" style="max-width: 960px">
        <form method="POST" action="{{ route('suppliers.update', $supplier) }}" novalidate>
            @csrf
            @method('PUT')
            <div class="card-body">@include('suppliers._form')</div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2">
                <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save Changes</button>
            </div>
        </form>
    </div>
@endsection
