@extends('layouts.app')

@section('title', 'Documents — Example Firm')

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-1">
        <div>
            <h1 class="mb-1">Documents</h1>
            <p class="text-secondary mb-4">1 248 files · 6.4 GB · encrypted at rest</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <x-ui.button variant="outline-secondary" size="sm">
                matter: all <x-ui.icon name="chevron-down" class="ms-1" />
            </x-ui.button>
            <x-ui.button variant="outline-secondary" size="sm">
                type: all <x-ui.icon name="chevron-down" class="ms-1" />
            </x-ui.button>
            <x-ui.button variant="primary" icon="upload" size="sm">Upload</x-ui.button>
        </div>
    </div>

    <div class="card mb-3" style="border-style: dashed;">
        <div class="card-body text-center">
            <p class="text-meta mb-0">drop files here — they attach to the matter you filter by, and appear in its ledger</p>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Matter</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Uploaded by</th>
                        <th>Reads</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-danger text-white">PDF</span>
                                <a href="#" class="text-reset text-decoration-none">bundle_of_documents.pdf</a>
                            </div>
                        </td>
                        <td class="text-meta">LAB/0134</td>
                        <td class="text-secondary">Bundle</td>
                        <td class="text-secondary">18.2 MB</td>
                        <td>T. Baloyi</td>
                        <td class="text-secondary">11</td>
                    </tr>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-dark text-white">DOC</span>
                                <a href="#" class="text-reset text-decoration-none">heads_of_argument_v3.docx</a>
                            </div>
                        </td>
                        <td class="text-meta">LAB/0134</td>
                        <td class="text-secondary">Pleading</td>
                        <td class="text-secondary">214 KB</td>
                        <td>L. Radebe</td>
                        <td class="text-secondary">4</td>
                    </tr>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-danger text-white">PDF</span>
                                <a href="#" class="text-reset text-decoration-none">notice_of_motion.pdf</a>
                            </div>
                        </td>
                        <td class="text-meta">MAT/0051</td>
                        <td class="text-secondary">Pleading</td>
                        <td class="text-secondary">1.1 MB</td>
                        <td>P. Mahlangu</td>
                        <td class="text-secondary">7</td>
                    </tr>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-danger text-white">PDF</span>
                                <a href="#" class="text-reset text-decoration-none">mandate_and_popia.pdf</a>
                            </div>
                        </td>
                        <td class="text-meta">LAB/0134</td>
                        <td class="text-secondary">Mandate</td>
                        <td class="text-secondary">640 KB</td>
                        <td>Reception</td>
                        <td class="text-secondary">2</td>
                    </tr>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-warning text-white">JPG</span>
                                <a href="#" class="text-reset text-decoration-none">payslips_feb_aug.jpg</a>
                            </div>
                        </td>
                        <td class="text-meta">LAB/0134</td>
                        <td class="text-secondary">Evidence</td>
                        <td class="text-secondary">4.6 MB</td>
                        <td>T. Baloyi</td>
                        <td class="text-secondary">3</td>
                    </tr>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-danger text-white">PDF</span>
                                <a href="#" class="text-reset text-decoration-none">charge_sheet.pdf</a>
                            </div>
                        </td>
                        <td class="text-meta">CRM/0088</td>
                        <td class="text-secondary">Evidence</td>
                        <td class="text-secondary">880 KB</td>
                        <td>P. Mahlangu</td>
                        <td class="text-secondary">9</td>
                    </tr>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-dark text-white">DOC</span>
                                <a href="#" class="text-reset text-decoration-none">settlement_draft.docx</a>
                            </div>
                        </td>
                        <td class="text-meta">MAT/0051</td>
                        <td class="text-secondary">Agreement</td>
                        <td class="text-secondary">96 KB</td>
                        <td>P. Mahlangu</td>
                        <td class="text-secondary">5</td>
                    </tr>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-danger text-white">PDF</span>
                                <a href="#" class="text-reset text-decoration-none">certificate_non_resolution.pdf</a>
                            </div>
                        </td>
                        <td class="text-meta">LAB/0134</td>
                        <td class="text-secondary">Certificate</td>
                        <td class="text-secondary">320 KB</td>
                        <td>T. Baloyi</td>
                        <td class="text-secondary">6</td>
                    </tr>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-success text-white">XLS</span>
                                <a href="#" class="text-reset text-decoration-none">leave_pay_calculation.xlsx</a>
                            </div>
                        </td>
                        <td class="text-meta">LAB/0134</td>
                        <td class="text-secondary">Schedule</td>
                        <td class="text-secondary">42 KB</td>
                        <td>L. Radebe</td>
                        <td class="text-secondary">2</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection
