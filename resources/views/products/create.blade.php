@extends('layouts.app')

@section('title', 'Add Product')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
    <li class="breadcrumb-item active" aria-current="page">Add</li>
@endsection

@section('content')
    <form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data" novalidate>
        @csrf
        @include('products._form')
        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('products.index') }}" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save Product</button>
        </div>
    </form>
@endsection
