@extends('layouts.app')

@section('title', $matter->client->full_name.' — '.$matter->reference.' — Example Firm')

@section('content')
    @php
        $attorney = $matter->responsibleAttorney();
    @endphp

    <p class="text-meta mb-2">
        <a href="{{ route('matters') }}" class="text-meta text-decoration-none">Matters</a> /
        <a href="{{ route('matters', ['type' => $matter->matter_type_id]) }}" class="text-meta text-decoration-none">{{ $matter->matterType->name }}</a> /
        <span>{{ $matter->reference }}</span>
    </p>

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
        <div>
            <h1 class="mb-1">{{ $matter->client->full_name }}</h1>
            <p class="text-secondary mb-0">{{ $matter->title }}</p>
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
        <div class="col-6 col-md-2">
            <div class="text-meta text-uppercase mb-1">Status</div>
            <div @class(['fw-bold', 'text-primary' => $matter->isOpen(), 'text-success' => $matter->isClosed()])>{{ $matter->isOpen() ? 'Open' : 'Closed' }}</div>
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
            <div class="text-meta text-uppercase mb-1">Opened</div>
            <div>{{ $matter->instructed_at?->format('d M Y') ?? '—' }}</div>
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
