@extends('layouts.app')

@section('title', __('Store Commission') . ' - Vendex')

@section('content')
    @livewire('manager.commission-drilldown', ['storeId' => $storeId])
@endsection
