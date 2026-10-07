@extends('layouts.app')

@section('title', 'Dashboard — Example Firm')

@section('content')
    @php
        $greeting = match (true) {
            now()->hour < 12 => 'Good morning',
            now()->hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };
    @endphp
    <h1 class="mb-1">{{ $greeting }}, {{ Str::before(auth()->user()->name, ' ') }}</h1>
    <p class="text-secondary mb-4">{{ now()->format('l d F Y') }}</p>

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
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0">Matters needing attention</h3>
                    <a href="#" class="ms-auto text-meta">view all</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Client</th>
                                <th>Stage</th>
                                <th>Next date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ([
                                ['ref' => 'EXF/2026/LAB/0134', 'client' => 'Mokoena, T', 'stage' => 'Arbitration', 'tone' => 'info', 'date' => '04 Sep'],
                                ['ref' => 'EXF/2026/CRM/0088', 'client' => 'Diale, R', 'stage' => 'Trial prep', 'tone' => 'danger', 'date' => '05 Sep'],
                                ['ref' => 'EXF/2026/MAT/0051', 'client' => 'Sithole & Sithole', 'stage' => 'Settlement', 'tone' => 'success', 'date' => '09 Sep'],
                                ['ref' => 'EXF/2026/LAB/0140', 'client' => 'Radebe, K', 'stage' => 'Pleadings', 'tone' => 'accent', 'date' => '11 Sep'],
                                ['ref' => 'EXF/2026/EST/0012', 'client' => 'Bafokeng Estate', 'stage' => 'Filing', 'tone' => 'neutral', 'date' => '15 Sep'],
                            ] as $matter)
                                <tr>
                                    <td class="text-meta">{{ $matter['ref'] }}</td>
                                    <td>{{ $matter['client'] }}</td>
                                    <td><x-ui.stage-badge :tone="$matter['tone']">{{ $matter['stage'] }}</x-ui.stage-badge></td>
                                    <td>{{ $matter['date'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0">Diary — today</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <p class="text-meta mb-0">09:00</p>
                        <p class="mb-0">Consultation — B. Diale</p>
                        <p class="text-meta mb-0">EXF/2026/CRM/0088</p>
                    </div>
                    <div class="mb-3">
                        <p class="text-meta mb-0">11:30</p>
                        <p class="mb-0">CCMA arbitration — T. Mokoena</p>
                        <p class="text-meta mb-0">EXF/2026/LAB/0134</p>
                    </div>
                    <div>
                        <p class="text-meta mb-0">16:00</p>
                        <p class="mb-0">File review</p>
                        <p class="text-meta mb-0">EXF/2026/EST/0012</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Recent narration</h3>
                </div>
                <div class="card-body">
                    <p class="mb-1">Telephone consultation with client re: settlement tender of R120 000.</p>
                    <p class="text-meta mb-3">T. Mokoena · 09:12</p>

                    <p class="mb-1">Filed heads of argument with CCMA registrar.</p>
                    <p class="text-meta mb-0">L. Phiri · yesterday</p>
                </div>
            </div>
        </div>
    </div>
@endsection
