@extends('layouts.app')

@section('title', 'Matters — Example Firm')

@section('content')
    <div class="d-flex align-items-start justify-content-between mb-1">
        <div>
            <h1 class="mb-1">Matters</h1>
            <p class="text-secondary mb-4">64 open · 3 unassigned · 12 closed this year</p>
        </div>
        
         <x-ui.button variant="primary" icon="plus" size="sm"
            x-data x-on:click="$dispatch('sheet-open', 'new-matter')">New Matter</x-ui.button>
    </div>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="d-flex align-items-center gap-2 bg-light rounded-pill p-1" role="group" aria-label="Matter filters">
            <x-ui.button variant="dark" class="rounded-pill" size="sm">All 64</x-ui.button>
            <x-ui.button variant="ghost-secondary" class="rounded-pill" size="sm">Mine 34</x-ui.button>
            <x-ui.button variant="ghost-secondary" class="rounded-pill" size="sm">Unassigned 3</x-ui.button>
            <x-ui.button variant="ghost-secondary" class="rounded-pill" size="sm">Closed</x-ui.button>
        </div>

        <div class="d-flex align-items-center gap-2 bg-light rounded-pill p-1">
            <x-ui.button variant="ghost-secondary" class="rounded-pill" size="sm">
                type: all <x-ui.icon name="chevron-down" class="ms-1" />
            </x-ui.button>
            <x-ui.button variant="ghost-secondary" class="rounded-pill" size="sm">
                stage: all <x-ui.icon name="chevron-down" class="ms-1" />
            </x-ui.button>
            <x-ui.button variant="ghost-secondary" class="rounded-pill" size="sm">
                sort: next date <x-ui.icon name="chevron-down" class="ms-1" />
            </x-ui.button>
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
                        <td class="text-meta"><a href="{{ route('matter.show') }}">NGA/2026/LAB/0134</a></td>
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

    <x-ui.sheet name="new-matter" title="Open a new matter" wide>
        <x-slot:header>
            <div class="d-flex align-items-start justify-content-between w-100">
                <div>
                    <h2 class="offcanvas-title mb-1">Open a new matter</h2>
                    <p class="text-meta mb-0">step 2 of 3 · matter details</p>
                </div>

                <div class="d-flex gap-1 mt-2">
                    <span class="bg-primary" style="width: 20px; height: 3px; border-radius: 999px;"></span>
                    <span class="bg-primary" style="width: 20px; height: 3px; border-radius: 999px;"></span>
                    <span class="bg-light" style="width: 20px; height: 3px; border-radius: 999px;"></span>
                </div>
            </div>
        </x-slot:header>

        <div class="d-flex align-items-start justify-content-between gap-3 p-3 mb-4 rounded"
             style="background-color: var(--cms-ink-900);">
            <div>
                <p class="text-uppercase mb-1" style="font-family: var(--cms-font-mono); font-size: var(--cms-text-2xs); letter-spacing: var(--cms-tracking-label-wide); color: var(--cms-gold-500);">
                    Reference to be issued
                </p>
                <p class="mb-0" style="font-family: var(--cms-font-mono); font-size: var(--cms-text-xl); color: #fff;">
                    NGA<span style="color: var(--cms-gold-500);"> / 2026 </span>/ LAB<span style="color: var(--cms-gold-500);"> / 0135</span>
                </p>
            </div>

            <p class="text-end mb-0 fst-italic" style="font-size: var(--cms-text-xs); color: var(--cms-on-ink-caption); max-width: 220px;">
                Firm code · year · matter-type code · sequence. Reserved by the API on save; the sequence you see is provisional.
            </p>
        </div>

        <form>
            <x-ui.input
                label="Client"
                name="client"
                before="Existing client or create new"
                hint="2 existing matters — check for duplicates"
                value="Mokoena, Thabo · 072 ··· 4418"
            />

            <x-ui.select
                label="Matter type"
                name="matter_type"
                before="Drives the reference code"
                hint="code LAB"
            >
                <option>Labour</option>
                <option>Criminal</option>
                <option>Matrimonial</option>
            </x-ui.select>

            <x-ui.select
                label="Sub-type"
                name="sub_type"
                before="For reporting only"
            >
                <option>Unfair dismissal</option>
                <option>Retrenchment</option>
                <option>Misconduct</option>
            </x-ui.select>

            <x-ui.input
                label="Matter title"
                name="matter_title"
                before="Appears on the file cover"
                value="Mokoena, T v Rustenburg Platinum Mines (Pty) Ltd"
            />

            <x-ui.select
                label="Responsible attorney"
                name="attorney"
                before="Who owns the file"
            >
                <option>L. Radebe</option>
                <option>P. Mahlangu</option>
            </x-ui.select>

            <x-ui.input
                label="Date of instruction"
                name="instructed_at"
                before="prescription clock starts here"
                value="14 / 08 / 2026"
            />

            <x-ui.input
                label="Opening narration"
                name="narration"
                before="Optional first ledger entry"
                value="Instruction received; mandate signed and POPIA consent captured."
            />
        </form>

        <div class="d-flex align-items-center justify-content-between">
            <x-ui.button variant="outline-secondary">
                <x-ui.icon name="arrow-left" class="me-1" /> Back
            </x-ui.button>

            <div class="d-flex gap-2">
                <x-ui.button variant="outline-secondary">Save draft</x-ui.button>
                <x-ui.button variant="primary">Continue to parties →</x-ui.button>
            </div>
        </div>
    </x-ui.sheet>
@endsection
