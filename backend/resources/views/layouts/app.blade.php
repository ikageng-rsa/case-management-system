@php
    $user = auth()->user();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

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
                :account-href="route('account')"
            >
                <x-ui.nav-section>Practice</x-ui.nav-section>
                <x-ui.nav-item :href="route('dashboard')" label="Dashboard" icon="layout-dashboard" :active="request()->routeIs('dashboard')" />
                <x-ui.nav-item :href="route('matters')" label="Matters" icon="briefcase" :active="request()->routeIs('matter*')" />
                @can('viewAny', \App\Models\Client::class)
                    <x-ui.nav-item :href="route('clients')" label="Clients" icon="users" :active="request()->routeIs('client*', 'clients')"/>
                @endcan
                <x-ui.nav-item :href="route('documents')" label="Documents" icon="file-text" :active="request()->routeIs('documents')"/>
                <x-ui.nav-item href="#" label="Reports" icon="chart-bar"/>

                @canany(['view_users', 'view_audit_log', 'manage_courts', 'manage_matter_types', 'manage_activity_types'])
                    <x-ui.nav-section>Firm</x-ui.nav-section>
                @endcanany
                @can('view_users')
                    <x-ui.nav-item :href="route('users')" label="Users" icon="users" :active="request()->routeIs('users')"/>
                @endcan
                @canany(['manage_courts', 'manage_matter_types', 'manage_activity_types'])
                    <x-ui.nav-item :href="route('reference-data')" label="Reference data" icon="database" :active="request()->routeIs('reference-data')"/>
                @endcanany
                @can('view_audit_log')
                    <x-ui.nav-item :href="route('audit-log')" label="Audit log" icon="history" :active="request()->routeIs('audit-log*')"/>
                @endcan

                <x-slot:footerActions>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        

                        <x-ui.button type="submit" variant="ghost-secondary" icon="logout" aria-label="Sign out" />
                    </form>
                </x-slot:footerActions>
            </x-ui.sidebar>

            <x-ui.header>
                {{-- What the system has told you. The list is fetched when the bell is opened. --}}
                <div class="position-relative" x-data="notificationMenu({{ \Illuminate\Support\Js::from(['url' => url('/notifications'), 'unread' => $user->unreadNotifications()->count()]) }})"
                     x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
                    <button type="button" class="btn btn-ghost-secondary btn-icon position-relative" x-on:click="toggle()"
                        x-bind:aria-expanded="open" aria-haspopup="true"
                        x-bind:aria-label="unread > 0 ? 'Notifications, ' + unread + ' unread' : 'Notifications'" title="Notifications">
                        <x-ui.icon name="bell" />
                        <span class="badge bg-primary text-dark position-absolute top-0 start-100 translate-middle rounded-pill"
                              x-show="unread > 0" x-cloak x-text="unread > 99 ? '99+' : unread"></span>
                    </button>

                    {{-- Anchored by its right edge to the bell, so it opens leftwards and never off the screen. --}}
                    <div class="dropdown-menu p-0" x-bind:class="{ show: open }" x-cloak
                         style="right: 0; left: auto; width: 24rem; max-width: calc(100vw - 2rem); max-height: 70vh; overflow-x: hidden; overflow-y: auto;">
                        <div class="d-flex align-items-center justify-content-between gap-2 px-3 py-2 border-bottom">
                            <span class="text-meta text-uppercase">Notifications</span>
                            <button type="button" class="btn btn-ghost-secondary btn-sm" x-show="unread > 0" x-cloak x-on:click="markAllRead()">Mark all as read</button>
                        </div>

                        <p class="text-secondary text-center py-4 mb-0" x-show="loading && ! loaded" x-cloak>Loading…</p>
                        <p class="text-secondary text-center py-4 mb-0" x-show="loaded && items.length === 0" x-cloak>Nothing has needed your attention yet.</p>

                        <template x-for="item in items" :key="item.id">
                            <div class="d-flex align-items-start gap-2 px-3 py-2 border-bottom" x-bind:class="{ 'bg-light': ! item.read }">
                                {{-- Not a .btn: a button lays its contents out in a row and keeps them on one line, and a
                                     message is a block of text with the time beneath it. --}}
                                <button type="button" class="d-block border-0 bg-transparent p-0 text-start flex-fill"
                                    style="min-width: 0; white-space: normal;"
                                    x-on:click="go(item)" x-bind:style="item.url ? '' : 'cursor: default;'">
                                    <span class="d-block text-body" x-bind:class="{ 'fw-bold': ! item.read }" x-text="item.message"></span>
                                    <span class="d-block text-meta mt-1" x-text="item.when"></span>
                                </button>

                                <button type="button" class="btn btn-ghost-secondary btn-icon btn-sm flex-shrink-0"
                                    x-show="! item.read" x-cloak x-on:click="markRead(item)"
                                    aria-label="Mark as read" title="Mark as read">
                                    <x-ui.icon name="check" />
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- A matter can be opened from anywhere; the form lives on the matters page, which this opens. --}}
                @can('create', \App\Models\Matter::class)
                    @if (request()->routeIs('matters'))
                        <x-ui.button variant="dark" icon="plus"
                            x-data x-on:click="$dispatch('sheet-open', 'new-matter')">New matter</x-ui.button>
                    @else
                        <x-ui.button variant="dark" icon="plus" :href="route('matters', ['new' => 1])">New matter</x-ui.button>
                    @endif
                @endcan
            </x-ui.header>

            <div class="page-wrapper">
                <div class="page-body">
                    <div class="container-xl">
                        @yield('content')
                    </div>
                </div>
            </div>
        </div>

        <x-ui.confirm />
        <x-ui.toasts />
    </body>
</html>
