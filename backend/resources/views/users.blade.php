@extends('layouts.app')

@section('title', 'Users & roles — Example Firm')

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-1">
        <div>
            <h1 class="mb-1">Users &amp; roles</h1>
            <p class="text-secondary mb-4">9 active · 1 invited · 2 disabled</p>
        </div>

        <x-ui.button variant="dark" icon="plus">Invite user</x-ui.button>
    </div>

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Last active</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar avatar-rounded bg-primary text-dark">LR</span>
                                <div>
                                    <div class="fw-bold">L. Radebe</div>
                                    <div class="text-meta">l.radebe@···.co.za</div>
                                </div>
                            </div>
                        </td>
                        <td>Director · attorney</td>
                        <td class="text-secondary">2 min ago</td>
                        <td><x-ui.stage-badge tone="success">Active</x-ui.stage-badge></td>
                    </tr>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar avatar-rounded bg-secondary-lt">PM</span>
                                <div>
                                    <div class="fw-bold">P. Mahlangu</div>
                                    <div class="text-meta">p.mahlangu@···.co.za</div>
                                </div>
                            </div>
                        </td>
                        <td>Attorney</td>
                        <td class="text-secondary">18 min ago</td>
                        <td><x-ui.stage-badge tone="success">Active</x-ui.stage-badge></td>
                    </tr>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar avatar-rounded bg-secondary-lt">TB</span>
                                <div>
                                    <div class="fw-bold">T. Baloyi</div>
                                    <div class="text-meta">t.baloyi@···.co.za</div>
                                </div>
                            </div>
                        </td>
                        <td>Secretary</td>
                        <td class="text-secondary">1 h ago</td>
                        <td><x-ui.stage-badge tone="success">Active</x-ui.stage-badge></td>
                    </tr>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar avatar-rounded bg-secondary-lt">KM</span>
                                <div>
                                    <div class="fw-bold">Karabo Mabe</div>
                                    <div class="text-meta">k.mabe@···.co.za</div>
                                </div>
                            </div>
                        </td>
                        <td>Practice manager</td>
                        <td class="text-secondary">yesterday</td>
                        <td><x-ui.stage-badge tone="success">Active</x-ui.stage-badge></td>
                    </tr>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar avatar-rounded bg-secondary-lt">SN</span>
                                <div>
                                    <div class="fw-bold">Sarah Nel</div>
                                    <div class="text-meta">s.nel@···.co.za</div>
                                </div>
                            </div>
                        </td>
                        <td>Candidate attorney</td>
                        <td class="text-secondary">—</td>
                        <td><x-ui.stage-badge tone="accent">Invited</x-ui.stage-badge></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <h3 class="text-meta text-uppercase mb-2">Permission matrix</h3>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Capability</th>
                        <th class="text-center">Director</th>
                        <th class="text-center">Attorney</th>
                        <th class="text-center">Secretary</th>
                        <th class="text-center">Manager</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>View all matters</td>
                        <td class="text-center">✓</td>
                        <td class="text-center fst-italic text-secondary">own</td>
                        <td class="text-center">✓</td>
                        <td class="text-center">✓</td>
                    </tr>
                    <tr>
                        <td>Create matter / issue reference</td>
                        <td class="text-center">✓</td>
                        <td class="text-center">✓</td>
                        <td class="text-center">✓</td>
                        <td class="text-center text-secondary">—</td>
                    </tr>
                    <tr>
                        <td>Add ledger entry</td>
                        <td class="text-center">✓</td>
                        <td class="text-center">✓</td>
                        <td class="text-center">✓</td>
                        <td class="text-center text-secondary">—</td>
                    </tr>
                    <tr>
                        <td>Amend entry after 24 h</td>
                        <td class="text-center">✓</td>
                        <td class="text-center text-secondary">—</td>
                        <td class="text-center text-secondary">—</td>
                        <td class="text-center text-secondary">—</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection
