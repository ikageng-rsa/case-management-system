@extends('layouts.guest')

@section('title', 'Forgot your password — Example Firm')

@section('content')
    <div class="row g-0 min-vh-100 bg-dark">
        <div class="col-lg-6 d-flex align-items-center">
            <div class="w-100 px-4 mx-auto" style="max-width: 380px;">
                <x-ui.logo-mark initial="E" class="mb-3" />

                <h1 class="mb-1 text-white">Forgot your password</h1>
                <p class="mb-4" style="color: var(--cms-on-ink-caption);">
                    Enter your work email and we will send a link to choose a new one.
                </p>

                @if (session('status'))
                    <div class="alert alert-success" role="status">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('password.email') }}">
                    @csrf

                    <x-ui.input label="Email" name="email" type="email" tone="dark" placeholder="name@example.com"
                        autocomplete="username" required autofocus :value="old('email')" :error="$errors->first('email')" />

                    <x-ui.button type="submit" class="w-100" size="lg">Send the link</x-ui.button>
                </form>

                <p class="mt-3 mb-0"><a href="{{ route('login') }}" class="text-meta" style="color: var(--cms-on-ink-caption);">Back to sign in</a></p>
                <p class="text-meta mt-3" style="color: var(--cms-on-ink-caption);">The link works once and expires in {{ config('auth.passwords.users.expire') }} minutes.</p>
            </div>
        </div>

        <div class="col-lg-6 d-none d-lg-flex align-items-center" style="border-left: 1px solid var(--cms-border-on-ink);"></div>
    </div>
@endsection
