@extends('layouts.app')

@section('title', 'Edit Product')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('products.index') }}">Products</a></li>
    <li class="breadcrumb-item"><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit</li>
@endsection

@section('content')
    <form method="POST" action="{{ route('products.update', $product) }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PUT')
        @include('products._form')
        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('products.show', $product) }}" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save Changes</button>
        </div>
    </form>
@endsection
