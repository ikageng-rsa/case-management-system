@extends('layouts.app')

@section('title', 'Documents — Example Firm')

@section('content')
    @php
        $query = fn (array $overrides) => array_filter(
            array_merge(['matter' => $matterId, 'type' => $kind?->value], $overrides),
        );

        $badge = fn (Spatie\MediaLibrary\MediaCollections\Models\Media $media) => match (strtolower($media->extension)) {
            'pdf' => ['PDF', 'bg-danger'],
            'doc', 'docx' => ['DOC', 'bg-dark'],
            'xls', 'xlsx', 'csv' => ['XLS', 'bg-success'],
            'jpg', 'jpeg', 'png', 'gif', 'webp' => [strtoupper($media->extension), 'bg-warning'],
            default => [strtoupper(Str::limit($media->extension, 4, '')) ?: 'FILE', 'bg-secondary'],
        };
    @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-1">
        <div>
            <h1 class="mb-1">Documents</h1>
            <p class="text-secondary mb-4">{{ number_format($fileCount, 0, '.', ' ') }} {{ Str::plural('file', $fileCount) }} · {{ $totalSize }} · encrypted at rest</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <div class="dropdown position-relative" x-data="{ open: false }" x-on:click.outside="open = false">
                <x-ui.button variant="outline-secondary" size="sm" x-on:click="open = ! open">
                    matter: {{ $filterMatters->firstWhere('id', $matterId)?->reference ?? 'all' }}
                    <x-ui.icon name="chevron-down" class="ms-1" />
                </x-ui.button>
                <div class="dropdown-menu dropdown-menu-end" x-bind:class="{ show: open }">
                    <a class="dropdown-item" href="{{ route('documents', $query(['matter' => null])) }}">All</a>
                    @foreach ($filterMatters as $matter)
                        <a class="dropdown-item" href="{{ route('documents', $query(['matter' => $matter->getKey()])) }}">{{ $matter->reference }}</a>
                    @endforeach
                </div>
            </div>

            <div class="dropdown position-relative" x-data="{ open: false }" x-on:click.outside="open = false">
                <x-ui.button variant="outline-secondary" size="sm" x-on:click="open = ! open">
                    type: {{ $kind ? str_replace('_', ' ', $kind->value) : 'all' }}
                    <x-ui.icon name="chevron-down" class="ms-1" />
                </x-ui.button>
                <div class="dropdown-menu dropdown-menu-end" x-bind:class="{ show: open }">
                    <a class="dropdown-item" href="{{ route('documents', $query(['type' => null])) }}">All</a>
                    @foreach ($kinds as $option)
                        <a class="dropdown-item" href="{{ route('documents', $query(['type' => $option->value])) }}">{{ Str::headline($option->value) }}</a>
                    @endforeach
                </div>
            </div>

            <x-ui.button variant="primary" icon="upload" size="sm"
                x-data x-on:click="$dispatch('sheet-open', 'upload-document')">Upload</x-ui.button>
        </div>
    </div>

    {{-- Planned: drop files straight onto the page
    <div class="card mb-3" style="border-style: dashed;">
        <div class="card-body text-center">
            <p class="text-meta mb-0">drop files here — they attach to the matter you filter by, and appear in its ledger</p>
        </div>
    </div>
    --}}

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
                        <th>Downloads</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        @php([$label, $tone] = $badge($document))
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge {{ $tone }} text-white">{{ $label }}</span>
                                    <a href="{{ route('matter.documents.download', [$document->model, $document->getKey()]) }}" class="text-reset text-decoration-none">{{ $document->file_name }}</a>
                                </div>
                            </td>
                            <td class="text-meta"><a href="{{ route('matter.show', [$document->model, 'tab' => 'documents']) }}">{{ $document->model->reference }}</a></td>
                            <td class="text-secondary">{{ Str::headline((string) $document->getCustomProperty('kind')) }}</td>
                            <td class="text-secondary text-nowrap">{{ $document->human_readable_size }}</td>
                            <td>{{ $uploadedBy[(string) $document->getKey()]?->name ?? '—' }}</td>
                            <td class="text-secondary">{{ $downloads[(string) $document->getKey()] ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-secondary py-4">No documents to show.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($documents->hasPages())
            <div class="card-footer d-flex align-items-center justify-content-between">
                <span class="text-meta">showing {{ $documents->count() }} of {{ $documents->total() }}</span>
                <div class="d-flex gap-3">
                    @if (! $documents->onFirstPage())
                        <a href="{{ $documents->previousPageUrl() }}" class="text-meta">← newer</a>
                    @endif
                    @if ($documents->hasMorePages())
                        <a href="{{ $documents->nextPageUrl() }}" class="text-meta">older →</a>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <x-ui.sheet name="upload-document" title="Upload a document" lede="File it on a matter you carry." bag="document"
        :action="route('documents.store')" enctype="multipart/form-data">
        <x-ui.matter-picker label="Matter" name="matter_id" scope="fileable" :selected="$uploadMatter"
            :error="$errors->document->first('matter_id')" />

        <x-ui.input label="File" name="file" id="upload_file" type="file" :error="$errors->document->first('file')" hint="Up to 50 MB." />

        <x-ui.select label="Kind" name="kind" id="upload_kind" :error="$errors->document->first('kind')">
            @foreach ($kinds as $option)
                <option value="{{ $option->value }}" @selected(old('kind') === $option->value)>{{ Str::headline($option->value) }}</option>
            @endforeach
        </x-ui.select>

        <x-slot:footer>
            <div class="d-flex align-items-center justify-content-between">
                <x-ui.button variant="outline-secondary" x-on:click="close()">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="primary">Upload</x-ui.button>
            </div>
        </x-slot:footer>
    </x-ui.sheet>
@endsection
