@extends('layouts.app')

@section('title', 'Audit log — Example Firm')

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-1">
        <div>
            <h1 class="mb-1">Audit log</h1>
            <p class="text-secondary mb-4">append-only · 7-year retention · no delete endpoint exists</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <x-ui.button variant="outline-secondary" size="sm">
                actor: all <x-ui.icon name="chevron-down" class="ms-1" />
            </x-ui.button>
            <x-ui.button variant="outline-secondary" size="sm">
                action: all <x-ui.icon name="chevron-down" class="ms-1" />
            </x-ui.button>
            <x-ui.button variant="outline-secondary" size="sm">export csv</x-ui.button>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Actor</th>
                        <th>Action</th>
                        <th>Subject</th>
                        <th>Matter</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-meta text-nowrap">03 Sep 09:14</td>
                        <td>L. Radebe</td>
                        <td class="text-meta text-secondary">read</td>
                        <td class="text-meta">narration#412</td>
                        <td class="text-meta">NGA/2026/LAB/0134</td>
                        <td class="text-meta">102.132.45.0</td>
                    </tr>
                    <tr>
                        <td class="text-meta text-nowrap">03 Sep 09:12</td>
                        <td>L. Radebe</td>
                        <td class="text-meta text-success">create</td>
                        <td class="text-meta">narration#412</td>
                        <td class="text-meta">NGA/2026/LAB/0134</td>
                        <td class="text-meta">102.132.45.0</td>
                    </tr>
                    <tr>
                        <td class="text-meta text-nowrap">03 Sep 08:52</td>
                        <td>T. Baloyi</td>
                        <td class="text-meta text-secondary">read</td>
                        <td class="text-meta">document#bundle.pdf</td>
                        <td class="text-meta">NGA/2026/LAB/0134</td>
                        <td class="text-meta">102.132.45.0</td>
                    </tr>
                    <tr>
                        <td class="text-meta text-nowrap">03 Sep 08:41</td>
                        <td>T. Baloyi</td>
                        <td class="text-meta text-secondary">login</td>
                        <td class="text-meta">session#a41f</td>
                        <td class="text-meta text-secondary">—</td>
                        <td class="text-meta">102.132.45.0</td>
                    </tr>
                    <tr>
                        <td class="text-meta text-nowrap">03 Sep 07:31</td>
                        <td>P. Mahlangu</td>
                        <td class="text-meta text-secondary">read</td>
                        <td class="text-meta">case#0134</td>
                        <td class="text-meta">NGA/2026/LAB/0134</td>
                        <td class="text-meta">41.203.18.0</td>
                    </tr>
                    <tr>
                        <td class="text-meta text-nowrap">02 Sep 16:02</td>
                        <td>K. Mabe</td>
                        <td class="text-meta text-primary">export</td>
                        <td class="text-meta">report#practice (pdf)</td>
                        <td class="text-meta text-secondary">—</td>
                        <td class="text-meta">41.203.18.0</td>
                    </tr>
                    <tr>
                        <td class="text-meta text-nowrap">02 Sep 15:41</td>
                        <td>T. Baloyi</td>
                        <td class="text-meta text-success">create</td>
                        <td class="text-meta">document#bundle.pdf</td>
                        <td class="text-meta">NGA/2026/LAB/0134</td>
                        <td class="text-meta">102.132.45.0</td>
                    </tr>
                    <tr>
                        <td class="text-meta text-nowrap">02 Sep 11:22</td>
                        <td>L. Radebe</td>
                        <td class="text-meta text-info">update</td>
                        <td class="text-meta">case#0134 stage</td>
                        <td class="text-meta">NGA/2026/LAB/0134</td>
                        <td class="text-meta">102.132.45.0</td>
                    </tr>
                    <tr>
                        <td class="text-meta text-nowrap">02 Sep 09:15</td>
                        <td class="text-meta text-secondary">—</td>
                        <td class="text-meta text-danger">login</td>
                        <td class="text-meta">failed · s.nel@···.co.za</td>
                        <td class="text-meta text-secondary">—</td>
                        <td class="text-meta">196.44.72.0</td>
                    </tr>
                    <tr>
                        <td class="text-meta text-nowrap">01 Sep 14:48</td>
                        <td>P. Mahlangu</td>
                        <td class="text-meta text-danger">delete</td>
                        <td class="text-meta">document#draft_v1.docx</td>
                        <td class="text-meta">NGA/2026/MAT/0051</td>
                        <td class="text-meta">41.203.18.0</td>
                    </tr>
                    <tr>
                        <td class="text-meta text-nowrap">01 Sep 11:05</td>
                        <td>L. Radebe</td>
                        <td class="text-meta text-success">create</td>
                        <td class="text-meta">narration#408</td>
                        <td class="text-meta">NGA/2026/LAB/0134</td>
                        <td class="text-meta">102.152.45.0</td>
                    </tr>
                    <tr>
                        <td class="text-meta text-nowrap">28 Aug 12:05</td>
                        <td>L. Radebe</td>
                        <td class="text-meta text-primary">export</td>
                        <td class="text-meta">ledger#0134 (csv)</td>
                        <td class="text-meta">NGA/2026/LAB/0134</td>
                        <td class="text-meta">102.152.45.0</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection
