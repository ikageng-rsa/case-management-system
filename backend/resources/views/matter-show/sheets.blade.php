<x-ui.sheet name="new-narration" title="Add a narration" lede="Record the work done on this matter." bag="narration" action="{{ route('matter.narrations.store', $matter) }}">
    <x-ui.select label="Activity" name="activity_type_id" :error="$errors->narration->first('activity_type_id')">
        @foreach ($activityTypes as $type)
            <option value="{{ $type->getKey() }}" @selected((int) old('activity_type_id') === $type->getKey())>{{ $type->name }} ({{ $type->unit_label }})</option>
        @endforeach
    </x-ui.select>

    <x-ui.input label="Quantity" name="quantity" type="number" step="0.01" min="0" :value="old('quantity')"
        hint="Minutes for timed work; otherwise pages, kilometres or items."
        :error="$errors->narration->first('quantity')" />

    <x-ui.select label="Court" name="court_id" before="Required for court appearances"
        :error="$errors->narration->first('court_id')">
        <option value="">—</option>
        @foreach ($courts as $court)
            <option value="{{ $court->getKey() }}" @selected((int) old('court_id') === $court->getKey())>{{ $court->label }}</option>
        @endforeach
    </x-ui.select>

    <x-ui.input label="When" name="occurred_at" type="datetime-local" :value="old('occurred_at')"
        hint="Leave empty for now." :error="$errors->narration->first('occurred_at')" />

    <div class="mb-3">
        <label class="form-label form-label-mono" for="narration-body">Attendance</label>
        <textarea class="form-control @error('body', 'narration') is-invalid @enderror" id="narration-body" name="body" rows="4">{{ old('body') }}</textarea>
        @error('body', 'narration')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <x-slot:footer>
        <div class="d-flex align-items-center justify-content-between">
            <x-ui.button variant="outline-secondary" x-on:click="close()">Cancel</x-ui.button>
            <x-ui.button type="submit" variant="primary">Save narration</x-ui.button>
        </div>
    </x-slot:footer>
</x-ui.sheet>

<x-ui.sheet name="new-diary-entry" title="Add a diary entry" lede="Something to be done by a date." bag="diary" action="{{ route('matter.diary.store', $matter) }}">
    <div class="mb-3">
        <label class="form-label form-label-mono" for="diary-body">Entry</label>
        <textarea class="form-control @error('body', 'diary') is-invalid @enderror" id="diary-body" name="body" rows="3">{{ old('body') }}</textarea>
        @error('body', 'diary')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <x-ui.input label="Due" name="due_at" type="datetime-local" :value="old('due_at')" :error="$errors->diary->first('due_at')" />

    <x-ui.select label="Assigned to" name="assigned_to" hint="Defaults to you." :error="$errors->diary->first('assigned_to')">
        <option value="">Me</option>
        @foreach ($team as $person)
            <option value="{{ $person->getKey() }}" @selected(old('assigned_to') === $person->getKey())>{{ $person->name }}</option>
        @endforeach
    </x-ui.select>

    <x-slot:footer>
        <div class="d-flex align-items-center justify-content-between">
            <x-ui.button variant="outline-secondary" x-on:click="close()">Cancel</x-ui.button>
            <x-ui.button type="submit" variant="primary">Save entry</x-ui.button>
        </div>
    </x-slot:footer>
</x-ui.sheet>

<x-ui.sheet name="upload-document" title="Upload a document" lede="File it on this matter." bag="document" action="{{ route('matter.documents.store', $matter) }}" enctype="multipart/form-data">
    <x-ui.input label="File" name="file" type="file" :error="$errors->document->first('file')" hint="Up to 50 MB." />

    <x-ui.select label="Kind" name="kind" :error="$errors->document->first('kind')">
        @foreach (App\Enums\Document\DocumentKind::cases() as $option)
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

@php
    // Responsible and supervising are held by one person, so taking the seat
    // replaces whoever has it; the form asks first when that is about to happen.
    $holders = collect($capacities)
        ->filter(fn ($capacity) => $capacity->isExclusive())
        ->mapWithKeys(function ($capacity) use ($matter) {
            $holder = $matter->assignments->first(fn ($assignment) => $assignment->capacity === $capacity)?->user;

            return [$capacity->value => $holder ? ['id' => $holder->getKey(), 'name' => $holder->name] : null];
        })
        ->filter();

    $assignState = [
        'userId' => (string) old('user_id', $assignable->first()?->getKey()),
        'capacity' => (string) old('capacity', $capacities[0]->value ?? ''),
        'holders' => $holders,
    ];
@endphp

<x-ui.sheet name="assign-member" title="Add a team member" lede="Assign someone to this matter." bag="assignment" action="{{ route('matter.assign', $matter) }}"
    :form-data="(string) \Illuminate\Support\Js::from($assignState)"
    data-confirm-title="Replace current holder" data-confirm-action="Replace" data-confirm-tone="primary" data-confirm-icon="users"
    x-bind:data-confirm="holders[capacity] && holders[capacity].id !== userId
        ? holders[capacity].name + ' is currently ' + capacity + ' on this matter. Assigning this person replaces them.'
        : null">
    <x-ui.select label="Person" name="user_id" x-model="userId" :error="$errors->assignment->first('user_id')">
        @foreach ($assignable as $person)
            <option value="{{ $person->getKey() }}" @selected(old('user_id') === $person->getKey())>{{ $person->name }}</option>
        @endforeach
    </x-ui.select>

    <x-ui.select label="Capacity" name="capacity" x-model="capacity" :error="$errors->assignment->first('capacity')"
        hint="Responsible and supervising are held by one person; taking the seat replaces the current holder.">
        @foreach ($capacities as $capacity)
            <option value="{{ $capacity->value }}" @selected(old('capacity') === $capacity->value)>{{ ucfirst($capacity->value) }}</option>
        @endforeach
    </x-ui.select>

    <x-slot:footer>
        <div class="d-flex align-items-center justify-content-between">
            <x-ui.button variant="outline-secondary" x-on:click="close()">Cancel</x-ui.button>
            <x-ui.button type="submit" variant="primary">Assign</x-ui.button>
        </div>
    </x-slot:footer>
</x-ui.sheet>
