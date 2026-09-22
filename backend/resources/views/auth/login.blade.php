<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Sign in — Ngunduza Attorneys</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="row g-0 min-vh-100">
            {{-- Left panel: the actual sign-in form --}}
            <div class="col-lg-6 d-flex align-items-center" style="background-color: var(--cms-ink-900);">
                <div class="w-100 px-4" style="max-width: 380px; margin-inline: auto;">
                    <x-ui.logo-mark initial="N" class="mb-3" />

                    <h1 class="mb-1" style="color: var(--cms-on-ink); font-family: var(--cms-font-display);">
                        Case management
                    </h1>
                    <p class="mb-4" style="color: var(--cms-on-ink-caption);">
                        Ngunduza Attorneys Inc. · staff access only
                    </p>

                    <x-ui.input
                        label="Email"
                        name="email"
                        type="email"
                        tone="dark"
                        placeholder="l.ngunduza@ngunduzaattorneys.co.za"
                    />

                    <x-ui.input
                        label="Password"
                        name="password"
                        type="password"
                        tone="dark"
                        placeholder="••••••••"
                    />

                    <x-ui.button class="w-100" size="lg">Sign in</x-ui.button>

                    <p class="text-meta mt-3">Sessions expire after 30 minutes idle. All access is logged.</p>
                </div>
            </div>

            {{-- Right panel: firm tagline, hidden on small screens --}}
            <div class="col-lg-6 d-none d-lg-flex align-items-center"
                 style="background-color: var(--cms-ink-900); border-left: 1px solid var(--cms-border-on-ink);">
                <div class="w-100 px-5" style="max-width: 420px; margin-inline: auto;">
                    <p class="text-meta mb-2" style="color: var(--cms-gold-500);">RUSTENBURG · EST. 2014</p>
                    <p style="color: var(--cms-on-ink); font-family: var(--cms-font-display); font-size: var(--cms-display-sm);">
                        Labour, criminal and matrimonial practice — 64 open matters, one file per client, one record of every attendance.
                    </p>
                </div>
            </div>
        </div>
    </body>
</html>