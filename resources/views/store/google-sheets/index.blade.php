@extends('layouts.appstore')

@section('title', __('Google Sheets Import') . ' - Vendex')

@section('content')
    <div class="max-w-4xl mx-auto space-y-6">
        @livewire('store.google-sheet-setup')
        @livewire('store.sheet-sync-health')
    </div>
@endsection
