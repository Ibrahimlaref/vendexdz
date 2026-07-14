@extends('layouts.appstore')
@section('title', 'Vendex - Configurer ' . ucfirst($platform))
@section('content')

<livewire:store.integration-setup :platform="$platform" />

@endsection
