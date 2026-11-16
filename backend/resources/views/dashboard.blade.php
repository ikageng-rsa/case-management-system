@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    @php
        $greeting = match (true) {
            now()->hour < 12 => 'Good morning',
            now()->hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };
    @endphp
    <h1 class="mb-1">{{ $greeting }}, {{ Str::before(auth()->user()->name, ' ') }}</h1>
    <p class="text-secondary mb-4">{{ now()->format('l d F Y') }}</p>

    @if ($worksOnMatters)
        @include('dashboard.matters')
    @elseif ($debtors)
        @include('dashboard.billing')
    @else
        <div class="card">
            <div class="card-body">
                <p class="text-secondary mb-0">You have not been put on a matter yet. When you are, your work shows here.</p>
            </div>
        </div>
    @endif
@endsection
