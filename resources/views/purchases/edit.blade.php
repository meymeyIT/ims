@extends('layouts.app')

@section('title', "Edit {$purchase->purchase_number}")

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('purchases.index') }}">Purchases</a></li>
    <li class="breadcrumb-item"><a href="{{ route('purchases.show', $purchase) }}">{{ $purchase->purchase_number }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit</li>
@endsection

@section('content')
    <form method="POST" action="{{ route('purchases.update', $purchase) }}" novalidate>
        @csrf
        @method('PUT')
        @include('purchases._form')
    </form>
@endsection
