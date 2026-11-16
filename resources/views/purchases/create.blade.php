@extends('layouts.app')

@section('title', 'Create Purchase')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('purchases.index') }}">Purchases</a></li>
    <li class="breadcrumb-item active" aria-current="page">Create</li>
@endsection

@section('content')
    <form method="POST" action="{{ route('purchases.store') }}" novalidate>
        @csrf
        @include('purchases._form')
    </form>
@endsection
