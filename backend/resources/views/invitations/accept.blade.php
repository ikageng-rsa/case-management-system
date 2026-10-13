@extends('layouts.guest')

@section('title', 'Accept your invitation — Example Firm')

@section('content')
    <div class="row g-0 min-vh-100 bg-dark">
        <div class="col-12 d-flex align-items-center">
            <div class="w-100 px-4 mx-auto" style="max-width: 380px;">
                <x-ui.logo-mark initial="E" class="mb-3" />

                <h1 class="mb-1 text-white">Welcome, {{ $invitation->name }}</h1>
                <p class="mb-4" style="color: var(--cms-on-ink-caption);">
                    Choose a password to finish setting up your access.
                </p>

                <form method="POST" action="{{ route('invitation.accept', $token) }}">
                    @csrf

                    <x-ui.input label="Email" name="email" type="email" tone="dark" :value="$invitation->email" disabled />

                    <x-ui.input
                        label="Password"
                        name="password"
                        type="password"
                        tone="dark"
                        autocomplete="new-password"
                        required
                        autofocus
                        :error="$errors->first('password')"
                    />

                    <x-ui.input
                        label="Confirm password"
                        name="password_confirmation"
                        type="password"
                        tone="dark"
                        autocomplete="new-password"
                        required
                    />

                    <x-ui.button type="submit" class="w-100" size="lg">Create my account</x-ui.button>
                </form>

                <p class="text-meta mt-3" style="color: var(--cms-on-ink-caption);">All access is logged.</p>
            </div>
        </div>
    </div>
@endsection
