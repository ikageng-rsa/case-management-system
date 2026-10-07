@extends('layouts.guest')

@section('title', 'Sign in — Example Firm')

@section('content')
    <div class="row g-0 min-vh-100 bg-dark">
        {{-- Left panel: the actual sign-in form --}}
        <div class="col-lg-6 d-flex align-items-center">
            <div class="w-100 px-4 mx-auto" style="max-width: 380px;">
                <x-ui.logo-mark initial="E" class="mb-3" />

                <h1 class="mb-1 text-white">
                    Case management
                </h1>
                <p class="mb-4" style="color: var(--cms-on-ink-caption);">
                    Example Firm Attorneys Inc. · staff access only
                </p>

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <x-ui.input
                        label="Email"
                        name="email"
                        type="email"
                        tone="dark"
                        placeholder="name@example.com"
                        autocomplete="username"
                        required
                        autofocus
                        :value="old('email')"
                        :error="$errors->first('email')"
                    />

                    <x-ui.input
                        label="Password"
                        name="password"
                        type="password"
                        tone="dark"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        required
                        :error="$errors->first('password')"
                    />

                    <x-ui.button type="submit" class="w-100" size="lg">Sign in</x-ui.button>
                </form>

                <p class="text-meta mt-3" style="color: var(--cms-on-ink-caption);">Sessions expire after 30 minutes idle. All access is logged.</p>
            </div>
        </div>

        {{-- Right panel: firm tagline, hidden on small screens --}}
        <div class="col-lg-6 d-none d-lg-flex align-items-center"
             style="border-left: 1px solid var(--cms-border-on-ink);">
            <div class="w-100 px-5 mx-auto" style="max-width: 420px;">
                <p class="text-meta mb-2" style="color: var(--cms-gold-500);">EXAMPLE CITY · EST. 2014</p>
                <p class="text-white" style="font-family: var(--cms-font-display); font-size: var(--cms-display-sm);">
                    Labour, criminal and matrimonial practice — 64 open matters, one file per client, one record of every attendance.
                </p>
            </div>
        </div>
    </div>
@endsection
