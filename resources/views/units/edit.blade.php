@extends('layouts.app')

@section('title', 'Edit Unit')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('units.index') }}">Units</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $unit->name }}</li>
@endsection

@section('content')
    <div class="card" style="max-width: 760px">
        <form method="POST" action="{{ route('units.update', $unit) }}" novalidate>
            @csrf
            @method('PUT')
            <div class="card-body">@include('units._form')</div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2">
                <a href="{{ route('units.index') }}" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save Changes</button>
            </div>
        </form>
    </div>
@endsection
