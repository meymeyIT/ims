@extends('layouts.app')

@section('title', 'Add Supplier')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('suppliers.index') }}">Suppliers</a></li>
    <li class="breadcrumb-item active" aria-current="page">Add</li>
@endsection

@section('content')
    <div class="card" style="max-width: 960px">
        <form method="POST" action="{{ route('suppliers.store') }}" novalidate>
            @csrf
            <div class="card-body">@include('suppliers._form')</div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2">
                <a href="{{ route('suppliers.index') }}" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save Supplier</button>
            </div>
        </form>
    </div>
@endsection
