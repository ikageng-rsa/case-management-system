@extends('layouts.app')

@section('title', 'Dashboard — Example Firm')

@section('content')
    <h1 class="mb-1">Welcome, {{ Str::before(auth()->user()->name, ' ') }}</h1>
    <p class="text-secondary mb-4">You're signed in to case management.</p>

    <div class="row row-cards">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title">Your account</h3>

                    <dl class="row mb-0">
                        <dt class="col-5 text-meta">EMAIL</dt>
                        <dd class="col-7">{{ auth()->user()->email }}</dd>

                        <dt class="col-5 text-meta">TWO-FACTOR</dt>
                        <dd class="col-7 mb-0">
                            @if (auth()->user()->hasEnabledTwoFactorAuthentication())
                                <x-ui.stage-badge tone="success">On</x-ui.stage-badge>
                            @else
                                <x-ui.stage-badge tone="danger">Off</x-ui.stage-badge>
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection
