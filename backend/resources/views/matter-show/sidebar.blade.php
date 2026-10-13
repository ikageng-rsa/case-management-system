<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="card-title text-meta text-uppercase mb-0">Team</h3>
            @if ($matter->isOpen())
                @can('assign', $matter)
                    <x-ui.button variant="ghost-secondary" icon="plus" size="sm" aria-label="Add team member"
                        x-data x-on:click="$dispatch('sheet-open', 'assign-member')" />
                @endcan
            @endif
        </div>

        @forelse ($matter->assignments as $assignment)
            <div @class(['mb-3' => ! $loop->last])>
                <div class="mb-0">{{ $assignment->user->name }}</div>
                <div class="text-meta">{{ ucfirst($assignment->capacity->value) }}</div>
            </div>
        @empty
            <p class="text-secondary mb-0">Nobody is assigned yet.</p>
        @endforelse
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h3 class="card-title text-meta text-uppercase mb-0">Related documents</h3>
            <a href="{{ route('matter.show', [$matter, 'tab' => 'documents']) }}" class="text-meta text-decoration-none">all {{ $recentDocuments->count() }} →</a>
        </div>

        @forelse ($recentDocuments->sortByDesc('created_at')->take(4) as $document)
            <div @class(['d-flex align-items-start gap-2', 'mb-3' => ! $loop->last])>
                <x-ui.icon :name="match (true) {
                    $document->mime_type === 'application/pdf' => 'file-type-pdf',
                    str_starts_with($document->mime_type, 'image/') => 'photo',
                    in_array($document->extension, ['doc', 'docx'], true) => 'file-type-doc',
                    default => 'file',
                }" />
                <span>{{ $document->name }}</span>
            </div>
        @empty
            <p class="text-secondary mb-0">No documents filed.</p>
        @endforelse
    </div>
</div>

@can('viewAudit', $matter)
    <div class="card" style="border-style: dashed;">
        <div class="card-body">
            <h3 class="card-title text-meta text-uppercase mb-3">Who has read this file</h3>

            @forelse ($recentReads as $read)
                <p class="text-meta mb-1">{{ $read->created_at->format('d M H:i') }} &nbsp; {{ $read->causer?->name ?? 'System' }}</p>
            @empty
                <p class="text-meta mb-1">No reads recorded.</p>
            @endforelse

            <p class="text-meta fst-italic mt-2 mb-0">Every read is written to the audit table.</p>
        </div>
    </div>
@endcan
