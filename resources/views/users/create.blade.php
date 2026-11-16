@extends('layouts.app')

@section('title', 'Add User')

@section('breadcrumb')
    <li class="breadcrumb-item">System</li>
    <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Users</a></li>
    <li class="breadcrumb-item active" aria-current="page">Add</li>
@endsection

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('users.store') }}" novalidate>
            @csrf
            <div class="card-body">
                @include('users._form')
            </div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2">
                <a href="{{ route('users.index') }}" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Create User</button>
            </div>
        </form>
    </div>
@endsection
