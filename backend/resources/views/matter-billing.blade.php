@extends('layouts.app')

@section('title', 'Billing — '.$matter->reference)

{{-- A matter's billing by itself: enough to know whose matter it is, and nothing of the file. --}}
@section('content')
    <p class="text-meta mb-2">
        @can('view_bills')
            <a href="{{ route('bills') }}" class="text-meta text-decoration-none">Bills</a> /
        @endcan
        <span>{{ $matter->reference }}</span>
    </p>

    <div class="mb-3">
        <h1 class="mb-1">{{ $matter->client->full_name }}</h1>
        <p class="text-secondary mb-0">
            Billing on {{ $matter->reference }} · {{ $matter->matterType->name }}@if ($matter->title) · {{ $matter->title }}@endif
        </p>
    </div>

    @include('matter-show.billing')
@endsection
