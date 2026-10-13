@php
    $link = fn (array $params = []) => route('matter.show', [$matter, 'tab' => 'documents'] + $params);
@endphp

<div class="card">
    <div class="card-header d-block">
        <div class="mb-2">
            <h3 class="card-title mb-0">Documents</h3>
            <p class="text-meta mb-0">{{ $allDocumentsCount }} filed on this matter</p>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2 bg-light rounded-pill p-1">
            <x-ui.button :variant="$kind ? 'ghost-secondary' : 'dark'" class="rounded-pill" size="sm"
                :href="$link()">all {{ $allDocumentsCount }}</x-ui.button>

            @foreach ($kinds as $option)
                <x-ui.button :variant="$kind === $option ? 'dark' : 'ghost-secondary'" class="rounded-pill" size="sm"
                    :href="$link(['kind' => $option->value])">{{ str_replace('_', ' ', $option->value) }} {{ $kindCounts[$option->value] ?? 0 }}</x-ui.button>
            @endforeach
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Kind</th>
                    <th>Size</th>
                    <th>Filed</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($documents as $document)
                    <tr>
                        <td>{{ $document->name }}</td>
                        <td class="text-meta text-secondary">{{ str_replace('_', ' ', $document->getCustomProperty('kind')) }}</td>
                        <td class="text-meta">{{ $document->human_readable_size }}</td>
                        <td class="text-meta text-nowrap">{{ $document->created_at->format('d M Y H:i') }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('matter.documents.download', [$matter, $document->getKey()]) }}" class="btn btn-sm btn-ghost-secondary">Download</a>

                            @can('deleteDocument', $matter)
                                <form method="POST" action="{{ route('matter.documents.destroy', [$matter, $document->getKey()]) }}" class="d-inline"
                                    data-confirm="Delete {{ $document->name }}? This cannot be undone."
                                    data-confirm-title="Delete document" data-confirm-action="Delete" data-confirm-tone="danger" data-confirm-icon="trash">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" variant="ghost-danger" size="sm">Delete</x-ui.button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-secondary py-4">No documents to show.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
