@php
    $link = fn (array $params = []) => route('matter.show', [$matter, 'tab' => 'diary'] + $params);

    // A view with nothing in it is not worth a button, unless it is the one being looked at.
    $views = collect(['pending', 'overdue', 'mine', 'completed'])
        ->filter(fn ($option) => $diaryCounts[$option] > 0 || $show === $option);
@endphp

<div class="card">
    <div class="card-header d-block">
        <div class="mb-2">
            <h3 class="card-title mb-0">Diary</h3>
            <p class="text-meta mb-0">{{ $diaryCounts['pending'] }} pending · {{ $diaryCounts['overdue'] }} overdue</p>
        </div>

        {{-- A single view is not a choice, so there is nothing to switch between. --}}
        @if ($views->count() > 1)
            <div class="d-flex flex-wrap align-items-center gap-2 bg-light rounded-pill p-1">
                @foreach ($views as $option)
                    <x-ui.button :variant="$show === $option ? 'dark' : 'ghost-secondary'" class="rounded-pill" size="sm"
                        :href="$link(['show' => $option])">{{ $option }} {{ $diaryCounts[$option] }}</x-ui.button>
                @endforeach
            </div>
        @endif
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>Due</th>
                    <th>Entry</th>
                    <th>Assigned to</th>
                    <th>Reminder</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td @class(['text-meta text-nowrap', 'text-danger' => $entry->isOverdue()])>{{ $entry->due_at->format('d M Y H:i') }}</td>
                        <td>{{ $entry->body }}</td>
                        <td>{{ $entry->assignee->name }}</td>
                        <td class="text-secondary text-nowrap">
                            @if ($entry->isComplete())
                                —
                            @else
                                {{ \App\Services\Diary\ReminderLead::label($entry->remind_before_minutes) }}

                                {{-- It is the assignee's to set: whoever made the entry only suggested it. --}}
                                @if ($canWork && $entry->assigned_to === auth()->id())
                                    <x-ui.button variant="ghost-secondary" size="sm" icon="bell"
                                        aria-label="Change when I am reminded" title="Change when I am reminded"
                                        x-data x-on:click="$dispatch('sheet-open', 'entry-reminder-{{ $entry->getKey() }}')" />
                                @endif
                            @endif
                        </td>
                        <td>
                            @if ($entry->isComplete())
                                <x-ui.stage-badge tone="success">Done {{ $entry->completed_at->format('d M') }}</x-ui.stage-badge>
                            @elseif ($entry->isOverdue())
                                <x-ui.stage-badge tone="danger">Overdue</x-ui.stage-badge>
                            @else
                                <x-ui.stage-badge tone="info">Pending</x-ui.stage-badge>
                            @endif
                        </td>
                        <td class="text-end">
                            @if ($canWork && $entry->isPending() && ! $entry->isDue())
                                <x-ui.button variant="ghost-secondary" size="sm" disabled
                                    title="Not due until {{ $entry->due_at->format('d M Y H:i') }}">Mark done</x-ui.button>
                            @elseif ($canWork && $entry->isPending())
                                <form method="POST" action="{{ route('matter.diary.complete', [$matter, $entry]) }}"
                                    data-confirm="Mark this diary entry as done? It cannot be marked pending again."
                                    data-confirm-title="Complete diary entry" data-confirm-action="Mark done"
                                    data-confirm-tone="primary" data-confirm-icon="circle-check">
                                    @csrf
                                    <x-ui.button type="submit" variant="ghost-secondary" size="sm">Mark done</x-ui.button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-secondary py-4">No diary entries to show.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($canWork)
    @foreach ($entries as $entry)
        @if ($entry->isPending() && $entry->assigned_to === auth()->id())
            @php($reminderBag = 'diary_reminder_'.$entry->getKey())

            <x-ui.sheet name="entry-reminder-{{ $entry->getKey() }}" title="When to remind me" :lede="$entry->body" :bag="$reminderBag" method="PATCH"
                :action="route('matter.diary.reminder', [$matter, $entry])">
                <p class="text-secondary">Due {{ $entry->due_at->format('d M Y H:i') }}.</p>

                <x-diary.reminder id="reminder_{{ $entry->getKey() }}" label="Remind me" :minutes="$entry->remind_before_minutes"
                    :use-old="$errors->getBag($reminderBag)->any()"
                    :error="$errors->getBag($reminderBag)->first('remind_value') ?: $errors->getBag($reminderBag)->first('remind')" />

                <x-slot:footer>
                    <div class="d-flex align-items-center justify-content-between">
                        <x-ui.button variant="outline-secondary" x-on:click="close()">Cancel</x-ui.button>
                        <x-ui.button type="submit" variant="primary">Save</x-ui.button>
                    </div>
                </x-slot:footer>
            </x-ui.sheet>
        @endif
    @endforeach
@endif
