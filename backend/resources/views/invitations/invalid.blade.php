@extends('layouts.guest')

@section('title', 'Invitation unavailable — Example Firm')

@section('content')
    <div class="row g-0 min-vh-100 bg-dark">
        <div class="col-12 d-flex align-items-center">
            <div class="w-100 px-4 mx-auto" style="max-width: 380px;">
                <x-ui.logo-mark initial="E" class="mb-3" />

                <h1 class="mb-1 text-white">This invitation can't be used</h1>
                <p class="mb-4" style="color: var(--cms-on-ink-caption);">
                    The link has expired, has already been used, or was withdrawn. Ask whoever invited you to send a new one.
                </p>

                <x-ui.button :href="route('login')" class="w-100" size="lg">Go to sign in</x-ui.button>
            </div>
        </div>
    </div>
@endsection
