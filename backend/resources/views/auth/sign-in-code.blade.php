@extends('layouts.guest')

@section('title', 'Enter your code — Example Firm')

@section('content')
    <div class="row g-0 min-vh-100 bg-dark">
        <div class="col-lg-6 d-flex align-items-center">
            <div class="w-100 px-4 mx-auto" style="max-width: 380px;">
                <x-ui.logo-mark initial="E" class="mb-3" />

                <h1 class="mb-1 text-white">Check your email</h1>
                <p class="mb-4" style="color: var(--cms-on-ink-caption);">
                    We sent a six digit code to {{ $email }}. Enter it to finish signing in.
                </p>

                @if (session('status'))
                    <div class="alert alert-success" role="status">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('sign-in-code.verify') }}">
                    @csrf

                    <x-ui.input
                        label="Code"
                        name="code"
                        tone="dark"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        placeholder="123456"
                        maxlength="6"
                        autofocus
                        :error="$errors->first('code')"
                    />

                    <x-ui.button type="submit" class="w-100" size="lg">Sign in</x-ui.button>
                </form>

                <form method="POST" action="{{ route('sign-in-code.resend') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="btn btn-link p-0 text-meta" style="color: var(--cms-on-ink-caption);">Send a new code</button>
                </form>

                <p class="text-meta mt-3" style="color: var(--cms-on-ink-caption);">The code works once and expires in {{ config('security.sign_in_code.minutes') }} minutes. All access is logged.</p>
            </div>
        </div>

        <div class="col-lg-6 d-none d-lg-flex align-items-center"
             style="border-left: 1px solid var(--cms-border-on-ink);">
            <div class="w-100 px-5 mx-auto" style="max-width: 420px;">
                <p class="text-meta mb-2" style="color: var(--cms-gold-500);">EXAMPLE CITY · EST. 2014</p>
                <p class="text-white" style="font-family: var(--cms-font-display); font-size: var(--cms-display-sm);">
                    Two steps in, so that what is on the file stays between the people who should see it.
                </p>
            </div>
        </div>
    </div>
@endsection
