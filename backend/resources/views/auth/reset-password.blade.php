@extends('layouts.guest')

@section('title', 'Choose a new password — Example Firm')

@section('content')
    <div class="row g-0 min-vh-100 bg-dark">
        <div class="col-lg-6 d-flex align-items-center">
            <div class="w-100 px-4 mx-auto" style="max-width: 380px;">
                <x-ui.logo-mark initial="E" class="mb-3" />

                <h1 class="mb-1 text-white">Choose a new password</h1>
                <p class="mb-4" style="color: var(--cms-on-ink-caption);">You will be signed out everywhere else.</p>

                <form method="POST" action="{{ route('password.update') }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $request->route('token') }}">

                    <x-ui.input label="Email" name="email" type="email" tone="dark" autocomplete="username" required
                        :value="old('email', $request->email)" :error="$errors->first('email')" />
                    <x-ui.input label="New password" name="password" type="password" tone="dark" autocomplete="new-password"
                        required autofocus :error="$errors->first('password')" />
                    <x-ui.input label="Confirm new password" name="password_confirmation" type="password" tone="dark"
                        autocomplete="new-password" required />

                    <x-ui.button type="submit" class="w-100" size="lg">Save password</x-ui.button>
                </form>
            </div>
        </div>

        <div class="col-lg-6 d-none d-lg-flex align-items-center" style="border-left: 1px solid var(--cms-border-on-ink);"></div>
    </div>
@endsection
