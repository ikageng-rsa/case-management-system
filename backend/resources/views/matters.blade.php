@extends('layouts.app')

@section('title', 'Matters — Example Firm')

@section('content')
    <div class="d-flex align-items-start justify-content-between mb-1">
        <div>
            <h1 class="mb-1">Matters</h1>
            <p class="text-secondary mb-4">64 open · 3 unassigned · 12 closed this year</p>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="d-flex align-items-center gap-2 bg-light rounded-pill p-1" role="group" aria-label="Matter filters">
            <button type="button" class="btn btn-dark btn-sm rounded-pill">All 64</button>
            <button type="button" class="btn btn-ghost-secondary btn-sm rounded-pill">Mine 34</button>
            <button type="button" class="btn btn-ghost-secondary btn-sm rounded-pill">Unassigned 3</button>
            <button type="button" class="btn btn-ghost-secondary btn-sm rounded-pill">Closed</button>
        </div>

        <div class="d-flex align-items-center gap-2 bg-light rounded-pill p-1">
            <button type="button" class="btn btn-ghost-secondary btn-sm rounded-pill">
                type: all <x-ui.icon name="chevron-down" class="ms-1" />
            </button>
            <button type="button" class="btn btn-ghost-secondary btn-sm rounded-pill">
                stage: all <x-ui.icon name="chevron-down" class="ms-1" />
            </button>
            <button type="button" class="btn btn-ghost-secondary btn-sm rounded-pill">
                sort: next date <x-ui.icon name="chevron-down" class="ms-1" />
            </button>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Client</th>
                        <th>Type</th>
                        <th>Stage</th>
                        <th>Attorney</th>
                        <th>Next date</th>
                        <th>Docs</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-meta">NGA/2026/LAB/0134</td>
                        <td>Mokoena, T</td>
                        <td class="text-secondary">Labour</td>
                        <td><x-ui.stage-badge tone="danger">Trial</x-ui.stage-badge></td>
                        <td>L. Radebe</td>
                        <td>15 Sep</td>
                        <td class="text-secondary">7</td>
                    </tr>
                    <tr>
                        <td class="text-meta">NGA/2026/CRM/0088</td>
                        <td>Sithole, B</td>
                        <td class="text-secondary">Criminal</td>
                        <td><x-ui.stage-badge tone="accent">Pleadings</x-ui.stage-badge></td>
                        <td>L. Radebe</td>
                        <td>09 Sep</td>
                        <td class="text-secondary">7</td>
                    </tr>
                    <tr>
                        <td class="text-meta">NGA/2026/MAT/0051</td>
                        <td>Van Wyk, A &amp; J</td>
                        <td class="text-secondary">Matrimonial</td>
                        <td><x-ui.stage-badge tone="info">CCMA</x-ui.stage-badge></td>
                        <td>P. Mahlangu</td>
                        <td>11 Sep</td>
                        <td class="text-secondary">22</td>
                    </tr>
                    <tr>
                        <td class="text-meta">NGA/2026/LAB/0129</td>
                        <td>Rustenburg Plat (Pty)</td>
                        <td class="text-secondary">Labour</td>
                        <td><x-ui.stage-badge tone="neutral">Intake</x-ui.stage-badge></td>
                        <td class="text-secondary">Unassigned</td>
                        <td class="text-secondary">—</td>
                        <td class="text-secondary">3</td>
                    </tr>
                    <tr>
                        <td class="text-meta">NGA/2026/CRM/0081</td>
                        <td>Dlamini, S</td>
                        <td class="text-secondary">Criminal</td>
                        <td><x-ui.stage-badge tone="danger">Trial</x-ui.stage-badge></td>
                        <td>P. Mahlangu</td>
                        <td class="text-danger">05 Sep</td>
                        <td><a href="#" class="text-primary">11</a></td>
                    </tr>
                    <tr>
                        <td class="text-meta">NGA/2026/MAT/0047</td>
                        <td>Khumalo, N</td>
                        <td class="text-secondary">Matrimonial</td>
                        <td><x-ui.stage-badge tone="accent">Pleadings</x-ui.stage-badge></td>
                        <td>L. Radebe</td>
                        <td>18 Sep</td>
                        <td class="text-secondary">9</td>
                    </tr>
                    <tr>
                        <td class="text-meta">NGA/2026/LAB/0125</td>
                        <td>Bafokeng Mining Co</td>
                        <td class="text-secondary">Labour</td>
                        <td><x-ui.stage-badge tone="success">Closed</x-ui.stage-badge></td>
                        <td>L. Radebe</td>
                        <td class="text-secondary">—</td>
                        <td class="text-secondary">31</td>
                    </tr>
                    <tr>
                        <td class="text-meta">NGA/2026/MAT/0044</td>
                        <td>Pretorius, H</td>
                        <td class="text-secondary">Matrimonial</td>
                        <td><x-ui.stage-badge tone="danger">Trial</x-ui.stage-badge></td>
                        <td>P. Mahlangu</td>
                        <td>22 Sep</td>
                        <td><a href="#" class="text-primary">16</a></td>
                    </tr>
                    <tr>
                        <td class="text-meta">NGA/2026/CRM/0075</td>
                        <td>Nkosi, T</td>
                        <td class="text-secondary">Criminal</td>
                        <td><x-ui.stage-badge tone="neutral">Intake</x-ui.stage-badge></td>
                        <td class="text-secondary">Unassigned</td>
                        <td class="text-secondary">—</td>
                        <td class="text-secondary">2</td>
                    </tr>
                    <tr>
                        <td class="text-meta">NGA/2026/LAB/0121</td>
                        <td>Maluleke, G</td>
                        <td class="text-secondary">Labour</td>
                        <td><x-ui.stage-badge tone="info">CCMA</x-ui.stage-badge></td>
                        <td>L. Radebe</td>
                        <td>26 Sep</td>
                        <td class="text-secondary">6</td>
                    </tr>
                    <tr>
                        <td class="text-meta">NGA/2026/MAT/0039</td>
                        <td>Botha, R &amp; L</td>
                        <td class="text-secondary">Matrimonial</td>
                        <td><x-ui.stage-badge tone="success">Closed</x-ui.stage-badge></td>
                        <td>P. Mahlangu</td>
                        <td class="text-secondary">—</td>
                        <td><a href="#" class="text-primary">12</a></td>
                    </tr>
                    <tr>
                        <td class="text-meta">NGA/2026/CRM/0070</td>
                        <td>Segwai, K</td>
                        <td class="text-secondary">Criminal</td>
                        <td><x-ui.stage-badge tone="accent">Pleadings</x-ui.stage-badge></td>
                        <td>L. Radebe</td>
                        <td>01 Oct</td>
                        <td class="text-secondary">5</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card-footer d-flex align-items-center justify-content-between">
            <span class="text-meta">showing 12 of 64</span>
            <a href="#" class="text-meta">load more →</a>
        </div>
    </div>
@endsection
