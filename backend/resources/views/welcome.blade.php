<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="d-flex" style="min-height: 100vh">
            <x-ui.sidebar
                firm="Example Firm"
                tagline="Attorneys Inc."
                user="Jane Doe"
                role="Director · attorney"
                initials="JD"
            >
                <x-ui.nav-section>Practice</x-ui.nav-section>
                <x-ui.nav-item href="#" label="Dashboard" icon="layout-dashboard" :count="42" active />
                <x-ui.nav-item href="#" label="Clients" icon="users" :count="218" />
            </x-ui.sidebar>

            <div class="flex-fill d-flex flex-column" style="min-width: 0">
                <x-ui.header>
                    <x-ui.button variant="dark" icon="plus">New matter</x-ui.button>
                </x-ui.header>

                <main class="flex-fill p-4">
                    <h1 class="mb-1">Component demo</h1>
                    <p class="text-secondary mb-4">These are standard components developed from design tokens.</p>

                    <div class="row row-cards mb-4">
                        <div class="col-sm-6 col-lg-3">
                            <x-ui.stat-card label="Open matters" value="64" delta="+3 this week" />
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <x-ui.stat-card label="Awaiting my action" value="11" delta="2 overdue" tone="danger" />
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <x-ui.stat-card label="Unbilled hours" value="38.5" delta="R 57 750" tone="secondary" />
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <x-ui.stat-card label="New intakes" value="6" delta="3 unassigned" tone="warning" />
                        </div>
                    </div>

                    <div class="row row-cards">
                        <div class="col-lg-6">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h3 class="card-title">Stage badges</h3>
                                    <div class="d-flex flex-wrap gap-2">
                                        <x-ui.stage-badge tone="neutral">Intake</x-ui.stage-badge>
                                        <x-ui.stage-badge tone="info">CCMA</x-ui.stage-badge>
                                        <x-ui.stage-badge tone="accent">Pleadings</x-ui.stage-badge>
                                        <x-ui.stage-badge tone="success">Closed</x-ui.stage-badge>
                                        <x-ui.stage-badge tone="danger">Trial</x-ui.stage-badge>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-body">
                                    <h3 class="card-title">Buttons</h3>
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <x-ui.button>Primary</x-ui.button>
                                        <x-ui.button variant="dark" icon="plus">New matter</x-ui.button>
                                        <x-ui.button variant="outline-primary">Outline</x-ui.button>
                                        <x-ui.button variant="ghost-secondary">Ghost</x-ui.button>
                                        <x-ui.button variant="danger" icon="alert-triangle">Overdue</x-ui.button>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 align-items-center">
                                        <x-ui.button size="lg">Large</x-ui.button>
                                        <x-ui.button size="sm">Small</x-ui.button>
                                        <x-ui.button href="#" variant="link">Link button</x-ui.button>
                                        <x-ui.button icon="bell" aria-label="Alerts" />
                                    </div>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-body">
                                    <h3 class="card-title">Logo mark</h3>
                                    <div class="d-flex align-items-center gap-3">
                                        <x-ui.logo-mark>E</x-ui.logo-mark>
                                        <x-ui.logo-mark size="sm">E</x-ui.logo-mark>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h3 class="card-title">Inputs</h3>
                                    <x-ui.input
                                        label="Client reference"
                                        name="reference"
                                        placeholder="REF/2026/LAB/0134"
                                    />
                                    <x-ui.input
                                        label="Attendance note"
                                        name="note"
                                        hint="Recorded against the matter ledger."
                                    />
                                    <x-ui.input
                                        label="Email"
                                        name="email"
                                        type="email"
                                        error="Enter a valid address."
                                    />
                                    <x-ui.search-field placeholder="Search clients, references…" />
                                </div>
                            </div>

                            <div class="card" style="background-color: var(--cms-ink-900)">
                                <div class="card-body">
                                    <h3 class="card-title" style="color: #fff">Inputs on ink</h3>
                                    <x-ui.input
                                        label="Email"
                                        name="login_email"
                                        type="email"
                                        tone="dark"
                                        placeholder="name@example.com"
                                    />
                                    <x-ui.input
                                        label="Password"
                                        name="login_password"
                                        type="password"
                                        tone="dark"
                                        placeholder="••••••••"
                                    />
                                    <x-ui.button class="w-100" size="lg">Sign in</x-ui.button>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
