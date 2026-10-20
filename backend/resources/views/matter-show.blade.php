@extends('layouts.app')

@section('title', $matter->client->full_name.' — '.$matter->reference.' — Example Firm')

@section('content')
    @php
        $attorney = $matter->responsibleAttorney();
        $canEditDetails = auth()->user()->can('editDetails', $matter);
    @endphp

    <p class="text-meta mb-2">
        <a href="{{ route('matters') }}" class="text-meta text-decoration-none">Matters</a> /
        <a href="{{ route('matters', ['type' => $matter->matter_type_id]) }}" class="text-meta text-decoration-none">{{ $matter->matterType->name }}</a> /
        <span>{{ $matter->reference }}</span>
    </p>

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
        <div>
            <h1 class="mb-1">{{ $matter->client->full_name }}</h1>
            @if ($matter->title || $canEditDetails)
                <x-matter.inline-field :matter="$matter" field="title" label="title" :value="$matter->title" :can-edit="$canEditDetails" placeholder="Title of the matter">
                    <span class="text-secondary">{{ $matter->title ?: 'Add a title' }}</span>
                </x-matter.inline-field>
            @endif
        </div>

        {{-- Work on a file belongs to the people assigned to it, while it is open. --}}
        @can('work', $matter)
            <div class="d-flex gap-2">
                <x-ui.button variant="outline-secondary" size="sm"
                    x-data x-on:click="$dispatch('sheet-open', 'new-diary-entry')">Add diary entry</x-ui.button>
                <x-ui.button variant="outline-secondary" size="sm"
                    x-data x-on:click="$dispatch('sheet-open', 'upload-document')">Upload document</x-ui.button>
                <x-ui.button variant="primary" icon="plus" size="sm"
                    x-data x-on:click="$dispatch('sheet-open', 'new-narration')">Narration</x-ui.button>
            </div>
        @endcan
    </div>

    <div class="row mb-3">
        <div class="col-6 col-md-2">
            <div class="text-meta text-uppercase mb-1">Reference</div>
            <div class="text-meta">{{ $matter->reference }}</div>
        </div>
        <div class="col-6 col-md-2">
            <div class="text-meta text-uppercase mb-1">Type</div>
            <div>{{ $matter->matterType->name }}</div>
        </div>
        @php
            $canClose = $matter->isOpen() && auth()->user()->can('close', $matter);
            $canReopen = $matter->isClosed() && auth()->user()->can('reopen', $matter);
        @endphp

        {{-- The value is the control: clicking it turns it into a field, and choosing a stage asks first. --}}
        <div class="col-6 col-md-2" x-data="{ editing: false }" x-on:click.outside="editing = false" x-on:keydown.escape.window="editing = false">
            <div class="text-meta text-uppercase mb-1">Status</div>

            @if ($canClose || $canReopen)
                {{-- The value and the field share one place: whichever is not showing is not there. The
                     toggle sits on a wrapper because a utility display class would beat the hiding. --}}
                <div x-show="! editing">
                    <button type="button" class="border-0 bg-transparent p-0 text-start" style="font: inherit;"
                        x-on:click="editing = true; $nextTick(() => $refs.stage.focus())" title="Change the status">
                        <span @class(['text-primary' => $matter->isOpen(), 'text-success' => $matter->isClosed()])>{{ $matter->isOpen() ? 'Open' : 'Closed' }}</span>
                        <x-ui.icon name="pencil" class="text-secondary ms-1" />
                    </button>
                </div>

                <div x-show="editing" x-cloak>
                    <select x-ref="stage" class="form-select form-select-sm py-0" style="min-height: 0; height: 1.75rem;"
                        x-on:change="
                            const chosen = $event.target.value;
                            editing = false;
                            $event.target.value = '{{ $matter->isOpen() ? 'open' : 'closed' }}';
                            if (chosen === 'closed') $refs.closeForm.requestSubmit();
                            if (chosen === 'open') $refs.reopenForm.requestSubmit();
                        ">
                        <option value="{{ $matter->isOpen() ? 'open' : 'closed' }}" selected>{{ $matter->isOpen() ? 'Open' : 'Closed' }}</option>
                        @if ($canClose)
                            <option value="closed">Closed</option>
                        @endif
                        @if ($canReopen)
                            <option value="open">Open</option>
                        @endif
                    </select>
                </div>

                @if ($canClose)
                    <form x-ref="closeForm" method="POST" action="{{ route('matter.close', $matter) }}" class="d-none"
                        data-confirm="Close matter {{ $matter->reference }}? Nothing more can be added to it: no narrations, diary entries, documents or people. This cannot be undone here; only someone allowed to reopen matters can, and that is recorded."
                        data-confirm-title="Close this matter?" data-confirm-action="Close matter" data-confirm-tone="danger" data-confirm-icon="lock">
                        @csrf
                    </form>
                @endif

                @if ($canReopen)
                    <form x-ref="reopenForm" method="POST" action="{{ route('matter.reopen', $matter) }}" class="d-none"
                        data-confirm="Reopen matter {{ $matter->reference }}? Work can be added to it again, and the reopening is recorded against your name."
                        data-confirm-title="Reopen this matter?" data-confirm-action="Reopen matter" data-confirm-tone="primary" data-confirm-icon="lock-open"
                        data-confirm-prompt-label="Why is it being reopened?" data-confirm-prompt-field="reason">
                        @csrf
                        <input type="hidden" name="reason" value="">
                    </form>
                @endif
            @else
                <div @class(['text-primary' => $matter->isOpen(), 'text-success' => $matter->isClosed()])>{{ $matter->isOpen() ? 'Open' : 'Closed' }}</div>
            @endif

            @if ($matter->isClosed())
                <div class="text-meta mt-1">since {{ $matter->closed_at->format('d M Y') }}</div>
            @endif
        </div>
        <div class="col-6 col-md-2">
            <div class="text-meta text-uppercase mb-1">Attorney</div>
            @if ($attorney)
                <div class="text-primary">{{ $attorney->name }}</div>
            @else
                <div class="text-secondary">Unassigned</div>
            @endif
        </div>
        <div class="col-6 col-md-2">
            <div class="text-meta text-uppercase mb-1">Next date</div>
            @if ($nextDate)
                <div @class(['text-danger' => $nextDate->isPast()])>{{ $nextDate->format('d M Y H:i') }}</div>
            @else
                <div class="text-secondary">—</div>
            @endif
        </div>
        <div class="col-6 col-md-2">
            <div class="text-meta text-uppercase mb-1">Instructed</div>
            <x-matter.inline-field :matter="$matter" field="instructed_at" label="date instructed" type="date"
                :value="$matter->instructed_at?->format('Y-m-d')" :can-edit="$canEditDetails">
                <span>{{ $matter->instructed_at?->format('d M Y') ?? '—' }}</span>
            </x-matter.inline-field>
        </div>
    </div>

    {{-- The particulars that belong to the kind of matter: where it is before, and when the claim is lost. --}}
    <div class="row mb-3">
        @if ($matter->matterType->is_litigation)
            <div class="col-6 col-md-4">
                <div class="text-meta text-uppercase mb-1">Court</div>
                <x-matter.inline-field :matter="$matter" field="court_id" label="court" :can-edit="$canEditDetails">
                    <span>{{ $matter->court?->label ?? '—' }}</span>

                    <x-slot:options>
                        <option value="">No court</option>
                        @foreach ($courts as $court)
                            <option value="{{ $court->getKey() }}" @selected($matter->court_id === $court->getKey())>{{ $court->label }}</option>
                        @endforeach
                    </x-slot:options>
                </x-matter.inline-field>
            </div>
        @endif

        <div class="col-6 col-md-4">
            <div class="text-meta text-uppercase mb-1">Prescribes</div>
            <x-matter.inline-field :matter="$matter" field="prescribes_at" label="prescription date" type="date"
                :value="$matter->prescribes_at?->format('Y-m-d')" :can-edit="$canEditDetails"
                confirm="The date a claim is lost on feeds the warnings that it is about to be. The change is recorded against your name, with your reason."
                prompt="Why is the date being changed?">
                <span @class(['text-danger' => $matter->hasPrescribed()])>{{ $matter->prescribes_at?->format('d M Y') ?? '—' }}</span>
            </x-matter.inline-field>
        </div>
    </div>

    @php
        $tabLabels = ['ledger' => 'Ledger', 'documents' => 'Documents', 'parties' => 'Parties', 'diary' => 'Diary', 'audit' => 'Audit'];
    @endphp

    <ul class="nav nav-underline mb-3">
        @foreach ($tabs as $key)
            <li class="nav-item">
                <a href="{{ route('matter.show', [$matter, 'tab' => $key]) }}" @class(['nav-link', 'active' => $tab === $key])>{{ $tabLabels[$key] }}</a>
            </li>
        @endforeach
    </ul>

    <div class="row row-cards">
        <div class="col-lg-8">
            @include('matter-show.'.$tab)
        </div>

        <div class="col-lg-4">
            @include('matter-show.sidebar')
        </div>
    </div>

    @include('matter-show.sheets')
@endsection
