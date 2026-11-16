@extends('layouts.app')

@section('title', 'Edit Category')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('categories.index') }}">Categories</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $category->name }}</li>
@endsection

@section('content')
    <div class="card" style="max-width: 760px">
        <form method="POST" action="{{ route('categories.update', $category) }}" novalidate>
            @csrf
            @method('PUT')
            <div class="card-body">@include('categories._form')</div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2">
                <a href="{{ route('categories.index') }}" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save Changes</button>
            </div>
        </form>
    </div>
@endsection
