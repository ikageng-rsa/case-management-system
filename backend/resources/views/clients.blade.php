@extends('layouts.app')

@section('title', 'Clients — Example Firm')

@section('content')
    <div class="row g-0">
        <div class="col-12 col-lg-4 border-end pe-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h1 class="mb-0">Clients</h1>
                        <span class="text-meta">218</span>
                    </div>

                    <x-ui.search-field placeholder="Name, ID number, phone…" :shortcut="false" wrapper="mb-3" class="w-100" />

                    <x-ui.button variant="primary" icon="plus" class="w-100 mb-3">New client intake</x-ui.button>

                    <div class="list-group list-group-flush">
                        <a href="#" class="list-group-item list-group-item-action d-flex align-items-start gap-2 px-0 py-2">
                            <span class="avatar avatar-rounded bg-dark text-white">TM</span>
                            <div class="flex-fill">
                                <div class="fw-bold">Thabo Mokoena</div>
                                <div class="text-meta">072 ··· 4418 · Rustenburg</div>
                            </div>
                            <div class="text-meta text-end">2<br>matters</div>
                        </a>

                        <a href="#" class="list-group-item list-group-item-action d-flex align-items-start gap-2 px-0 py-2">
                            <span class="avatar avatar-rounded bg-dark text-white">BS</span>
                            <div class="flex-fill">
                                <div class="fw-bold">Bongani Sithole</div>
                                <div class="text-meta">083 ··· 9201 · Phokeng</div>
                            </div>
                            <div class="text-meta text-end">1<br>matters</div>
                        </a>

                        <a href="#" class="list-group-item list-group-item-action d-flex align-items-start gap-2 px-0 py-2">
                            <span class="avatar avatar-rounded bg-info text-white">RP</span>
                            <div class="flex-fill">
                                <div class="fw-bold">Rustenburg Plat (Pty) Ltd</div>
                                <div class="text-meta">reg 2011/···/07 · corporate</div>
                            </div>
                            <div class="text-meta text-end">4<br>matters</div>
                        </a>

                        <a href="#" class="list-group-item list-group-item-action d-flex align-items-start gap-2 px-0 py-2">
                            <span class="avatar avatar-rounded bg-dark text-white">AV</span>
                            <div class="flex-fill">
                                <div class="fw-bold">Annelie van Wyk</div>
                                <div class="text-meta">061 ··· 7734 · Rustenburg</div>
                            </div>
                            <div class="text-meta text-end">1<br>matters</div>
                        </a>

                        <a href="#" class="list-group-item list-group-item-action d-flex align-items-start gap-2 px-0 py-2">
                            <span class="avatar avatar-rounded bg-dark text-white">NK</span>
                            <div class="flex-fill">
                                <div class="fw-bold">Nomsa Khumalo</div>
                                <div class="text-meta">078 ··· 2255 · Tlhabane</div>
                            </div>
                            <div class="text-meta text-end">1<br>matters</div>
                        </a>

                        <a href="#" class="list-group-item list-group-item-action d-flex align-items-start gap-2 px-0 py-2">
                            <span class="avatar avatar-rounded bg-dark text-white">SD</span>
                            <div class="flex-fill">
                                <div class="fw-bold">Sipho Dlamini</div>
                                <div class="text-meta">071 ··· 6640 · Marikana</div>
                            </div>
                            <div class="text-meta text-end">2<br>matters</div>
                        </a>

                        <a href="#" class="list-group-item list-group-item-action d-flex align-items-start gap-2 px-0 py-2">
                            <span class="avatar avatar-rounded bg-dark text-white">HP</span>
                            <div class="flex-fill">
                                <div class="fw-bold">Hendrik Pretorius</div>
                                <div class="text-meta">082 ··· 1100 · Rustenburg</div>
                            </div>
                            <div class="text-meta text-end">1<br>matters</div>
                        </a>

                        <a href="#" class="list-group-item list-group-item-action d-flex align-items-start gap-2 px-0 py-2">
                            <span class="avatar avatar-rounded bg-dark text-white">GM</span>
                            <div class="flex-fill">
                                <div class="fw-bold">Grace Maluleke</div>
                                <div class="text-meta">076 ··· 3391 · Boitekong</div>
                            </div>
                            <div class="text-meta text-end">1<br>matters</div>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8 ps-lg-4">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="avatar avatar-rounded bg-dark text-white" style="--tblr-avatar-size: 3rem; font-size: 1rem;">TM</span>
                    <div>
                        <h2 class="mb-1">Thabo Mokoena</h2>
                        <p class="text-meta mb-0">072 ··· 4418 · t.mokoena@····.com · 8402145····· · Rustenburg</p>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <x-ui.button variant="outline-secondary" size="sm">Edit</x-ui.button>
                    <x-ui.button variant="primary" icon="plus" size="sm">New matter</x-ui.button>
                </div>
            </div>

            <div class="row row-cards mb-3">
                <div class="col-4">
                    <x-ui.stat-card label="Open matters" value="1" />
                </div>
                <div class="col-4">
                    <x-ui.stat-card label="Fees outstanding" value="R 4 200" />
                </div>
                <div class="col-4">
                    <x-ui.stat-card label="Client since" value="Aug 2026" />
                </div>
            </div>

            <div class="row mb-3">
                <div class="col">
                    <div class="card">
                        <div class="card-body">
                            <h3 class="text-meta mb-2">Matters</h3>
                            <div class="table-responsive">
                                <table class="table table-vcenter">
                                    <tbody>
                                        <tr>
                                            <td class="text-meta text-nowrap">NGA/2026/LAB/0134</td>
                                            <td>v Rustenburg Platinum Mines</td>
                                            <td><x-ui.stage-badge tone="danger">Trial</x-ui.stage-badge></td>
                                            <td class="text-meta text-end">14 Aug 26</td>
                                        </tr>
                                        <tr>
                                            <td class="text-meta text-nowrap">NGA/2025/LAB/0092</td>
                                            <td>Retrenchment consultation</td>
                                            <td><x-ui.stage-badge tone="success">Closed</x-ui.stage-badge></td>
                                            <td class="text-meta text-end">03 Nov 25</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            

            <div class="row">
                <div class="col-md-6">
                    <h3 class="text-meta mb-2">Popia consent</h3>
                    <p class="mb-0">Captured 14 Aug 2026 · signed mandate on file · marketing opt-out</p>
                </div>
                <div class="col-md-6">
                    <h3 class="text-meta mb-2">Conflict check</h3>
                    <p class="mb-0">Cleared 14 Aug 2026 by L. Radebe · no adverse party match</p>
                </div>
            </div>
        </div>
    </div>
@endsection
