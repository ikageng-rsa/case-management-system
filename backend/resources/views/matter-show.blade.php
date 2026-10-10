@extends('layouts.app')

@section('title', 'Mokoena, T — NGA/2026/LAB/0134 — Example Firm')

@section('content')
    <p class="text-meta mb-2">
        <a href="#" class="text-meta text-decoration-none">Matters</a> /
        <a href="#" class="text-meta text-decoration-none">Labour</a> /
        <span>NGA/2026/LAB/0134</span>
    </p>

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
        <div>
            <h1 class="mb-1">Mokoena, T</h1>
            <p class="text-secondary mb-0">Unfair dismissal · Rustenburg Platinum Mines (Pty) Ltd</p>
        </div>

        <div class="d-flex gap-2">
            <x-ui.button variant="outline-secondary" size="sm">Add diary entry</x-ui.button>
            <x-ui.button variant="outline-secondary" size="sm">Upload document</x-ui.button>
            <x-ui.button variant="primary" icon="plus" size="sm">Narration</x-ui.button>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-6 col-md-2">
            <div class="text-meta mb-1">Reference</div>
            <div class="text-meta">NGA/2026/LAB/0134</div>
        </div>
        <div class="col-6 col-md-2">
            <div class="text-meta mb-1">Type</div>
            <div>Labour · unfair dismissal</div>
        </div>
        <div class="col-6 col-md-2">
            <div class="text-meta mb-1">Stage</div>
            <div class="text-primary fw-bold">Arbitration</div>
        </div>
        <div class="col-6 col-md-2">
            <div class="text-meta mb-1">Attorney</div>
            <div class="text-primary">L. Radebe</div>
        </div>
        <div class="col-6 col-md-2">
            <div class="text-meta mb-1">Next date</div>
            <div>04 Sep 2026 08:30</div>
        </div>
        <div class="col-6 col-md-2">
            <div class="text-meta mb-1">Opened</div>
            <div>14 Aug 2026</div>
        </div>
    </div>

    <ul class="nav nav-underline mb-3">
        <li class="nav-item">
            <a href="#" class="nav-link active">Ledger</a>
        </li>
        <li class="nav-item">
            <a href="#" class="nav-link">Documents</a>
        </li>
        <li class="nav-item">
            <a href="#" class="nav-link">Parties</a>
        </li>
        <li class="nav-item">
            <a href="#" class="nav-link">Diary</a>
        </li>
        <li class="nav-item">
            <a href="#" class="nav-link">Audit</a>
        </li>
    </ul>

    <div class="row row-cards">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-block">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <div>
                            <h3 class="card-title mb-0">Attendance ledger</h3>
                            <p class="text-meta mb-0">42 entries · 24.6 h recorded · 18.2 h billed</p>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2 bg-light rounded-pill p-1">
                        <x-ui.button variant="dark" class="rounded-pill" size="sm">all 42</x-ui.button>
                        <x-ui.button variant="ghost-secondary" class="rounded-pill" size="sm">calls 11</x-ui.button>
                        <x-ui.button variant="ghost-secondary" class="rounded-pill" size="sm">filings 6</x-ui.button>
                        <x-ui.button variant="ghost-secondary" class="rounded-pill" size="sm">court 4</x-ui.button>
                        <x-ui.button variant="ghost-secondary" class="rounded-pill" size="sm">mine</x-ui.button>
                        <x-ui.button variant="ghost-secondary" class="rounded-pill" size="sm">export</x-ui.button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>By</th>
                                <th>Attendance</th>
                                <th>Units</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="text-meta text-nowrap">03 Sep 09:12</td>
                                <td class="text-meta text-info">call</td>
                                <td class="text-meta">L.R.</td>
                                <td>Telephone consultation with client re: R120 000 settlement tender; instructed to counter at R185 000 plus leave pay.</td>
                                <td class="text-secondary">0.4</td>
                            </tr>
                            <tr>
                                <td class="text-meta text-nowrap">02 Sep 15:40</td>
                                <td class="text-meta text-success">filing</td>
                                <td class="text-meta">T.B.</td>
                                <td>Bundle indexed and paginated pp.1–214; served on CCMA registrar by email.</td>
                                <td class="text-secondary">1.1</td>
                            </tr>
                            <tr>
                                <td class="text-meta text-nowrap">02 Sep 11:20</td>
                                <td class="text-meta text-secondary">note</td>
                                <td class="text-meta">L.R.</td>
                                <td>Drafted heads of argument, third revision — procedural fairness point on chairperson's findings.</td>
                                <td class="text-secondary">0.6</td>
                            </tr>
                            <tr>
                                <td class="text-meta text-nowrap">01 Sep 11:05</td>
                                <td class="text-meta text-secondary">note</td>
                                <td class="text-meta">L.R.</td>
                                <td>Reviewed employer's disciplinary record; substantive charge not recorded.</td>
                                <td class="text-secondary">0.5</td>
                            </tr>
                            <tr>
                                <td class="text-meta text-nowrap">28 Aug 08:30</td>
                                <td class="text-meta text-warning">court</td>
                                <td class="text-meta">L.R.</td>
                                <td>Pre-arbitration conference; minute signed, arbitration set down 04 September 2026 08:30.</td>
                                <td class="text-secondary">2.5</td>
                            </tr>
                            <tr>
                                <td class="text-meta text-nowrap">26 Aug 16:10</td>
                                <td class="text-meta text-info">call</td>
                                <td class="text-meta">T.B.</td>
                                <td>Call to respondent's attorneys confirming availability of witnesses.</td>
                                <td class="text-secondary">0.2</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-body">
                    <h3 class="card-title text-meta mb-3">Parties</h3>

                    <div class="mb-3">
                        <div class="mb-0">Thabo Mokoena</div>
                        <div class="text-meta">Applicant · client</div>
                    </div>

                    <div class="mb-3">
                        <div class="mb-0">Rustenburg Platinum Mines (Pty) Ltd</div>
                        <div class="text-meta">Respondent · employer</div>
                    </div>

                    <div>
                        <div class="mb-0">Adv. S. Mahlangu</div>
                        <div class="text-meta">Counsel briefed · 28 Aug 2026</div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h3 class="card-title text-meta mb-0">Related documents</h3>
                        <a href="#" class="text-meta text-decoration-none">all 14 →</a>
                    </div>

                    <div class="d-flex align-items-start gap-2 mb-3">
                        <x-ui.icon name="file-type-pdf" class="text-danger" />
                        <span>Bundle of documents (214pp)</span>
                    </div>

                    <div class="d-flex align-items-start gap-2 mb-3">
                        <x-ui.icon name="file-type-pdf" class="text-danger" />
                        <span>LRA 7.11 referral form</span>
                    </div>

                    <div class="d-flex align-items-start gap-2 mb-3">
                        <x-ui.icon name="file-type-doc" class="text-info" />
                        <span>Heads of argument – draft 3</span>
                    </div>

                    <div class="d-flex align-items-start gap-2">
                        <x-ui.icon name="photo" class="text-warning" />
                        <span>Payslips Feb–Aug 2026</span>
                    </div>
                </div>
            </div>

            {{-- <div class="card mb-3">
                <div class="card-body">
                    <h3 class="card-title text-meta mb-3">Fees</h3>

                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary">Recorded</span>
                        <span>24.6 h</span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary">Billed</span>
                        <span>R 18 400</span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="text-secondary">Outstanding</span>
                        <span>R 4 200</span>
                    </div>
                </div>
            </div> --}}

            <div class="card" style="border-style: dashed;">
                <div class="card-body">
                    <h3 class="card-title text-meta mb-3">Who has read this file</h3>

                    <p class="text-meta mb-1">09:14 &nbsp; L.Radebe &nbsp; read &nbsp; narration#412</p>
                    <p class="text-meta mb-1">08:52 &nbsp; T.Baloyi &nbsp; read &nbsp; doc:bundle.pdf</p>
                    <p class="text-meta mb-3">07:31 &nbsp; P.Motaung &nbsp; read &nbsp; case#0134</p>

                    <p class="text-meta fst-italic mb-0">Every read is written to the audit table; contents encrypted at rest.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
