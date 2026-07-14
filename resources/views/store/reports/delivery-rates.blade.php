@extends('layouts.appstore')

@section('title', __('Delivery Rates') . ' - Vendex')

@section('content')
    @livewire('store.delivery-rate-stats')
@endsection
