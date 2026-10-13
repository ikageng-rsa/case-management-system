<div class="card mb-3">
    <div class="card-body">
        <h3 class="card-title text-meta text-uppercase mb-3">Client</h3>

        <div class="mb-3">
            <div class="mb-0">{{ $matter->client->full_name }}</div>
            <div class="text-meta">{{ ucfirst($matter->client->type->value) }}</div>
        </div>

        @foreach ($contacts as $contact)
            <div @class(['mb-3' => ! $loop->last])>
                <div class="mb-0">{{ $contact->value }}</div>
                <div class="text-meta">{{ str_replace('-', ' ', $contact->kind->value) }}{{ $contact->is_primary ? ' · primary' : '' }}</div>
            </div>
        @endforeach

        @if ($matter->court)
            <hr>
            <div class="mb-0">{{ $matter->court->label }}</div>
            <div class="text-meta">Court</div>
        @endif
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <h3 class="card-title text-meta text-uppercase mb-3">Assignments</h3>

        @forelse ($matter->assignments as $assignment)
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <div class="mb-0">{{ $assignment->user->name }}</div>
                    <div class="text-meta">{{ ucfirst($assignment->capacity->value) }} · since {{ $assignment->assigned_at->format('d M Y') }}</div>
                </div>

                @can('assign', $matter)
                    <form method="POST" action="{{ route('matter.unassign', $matter) }}"
                        data-confirm="Remove {{ $assignment->user->name }} as {{ $assignment->capacity->value }}?"
                        data-confirm-title="Remove from matter" data-confirm-action="Remove" data-confirm-tone="danger" data-confirm-icon="user-minus">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="user_id" value="{{ $assignment->user_id }}">
                        <input type="hidden" name="capacity" value="{{ $assignment->capacity->value }}">
                        <x-ui.button type="submit" variant="ghost-danger" size="sm">Remove</x-ui.button>
                    </form>
                @endcan
            </div>
        @empty
            <p class="text-secondary">Nobody is assigned yet.</p>
        @endforelse
    </div>
</div>
