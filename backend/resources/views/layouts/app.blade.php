@php
    $user = auth()->user();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title', config('app.name', 'Laravel'))</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="page">
            <x-ui.sidebar
                firm="Example Firm"
                tagline="Attorneys Inc."
                :user="$user->name"
                :role="Str::headline($user->getRoleNames()->first() ?? '')"
                :initials="Str::initials($user->name, capitalize: true)"
            >
                <x-ui.nav-section>Practice</x-ui.nav-section>
                <x-ui.nav-item :href="route('dashboard')" label="Dashboard" icon="layout-dashboard" :active="request()->routeIs('dashboard')" />
            </x-ui.sidebar>

            <x-ui.header>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-ui.button type="submit" variant="ghost-secondary" icon="logout">Sign out</x-ui.button>
                </form>
            </x-ui.header>

            <div class="page-wrapper">
                <div class="page-body">
                    <div class="container-xl">
                        @yield('content')
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
