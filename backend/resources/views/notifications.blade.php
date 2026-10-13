@extends('layouts.app')

@section('title', 'Notifications — Example Firm')

@section('content')
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-1">
        <div>
            <h1 class="mb-1">Notifications</h1>
            <p class="text-secondary mb-4">{{ $unreadCount }} unread. You choose what appears here, and what is emailed, from <a href="{{ route('account') }}">your account</a>.</p>
        </div>

        @if ($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <x-ui.button type="submit" variant="outline-secondary" size="sm">Mark all as read</x-ui.button>
            </form>
        @endif
    </div>

    <div class="d-flex align-items-center gap-2 bg-light rounded-pill p-1 mb-3" style="width: fit-content;">
        <x-ui.button :variant="$show === 'all' ? 'dark' : 'ghost-secondary'" class="rounded-pill" size="sm" :href="route('notifications')">all</x-ui.button>
        <x-ui.button :variant="$show === 'unread' ? 'dark' : 'ghost-secondary'" class="rounded-pill" size="sm" :href="route('notifications', ['show' => 'unread'])">unread {{ $unreadCount }}</x-ui.button>
    </div>

    <div class="card">
        <div class="list-group list-group-flush">
            @forelse ($notifications as $notification)
                @php
                    $message = $notification->data['message'] ?? 'Something needs your attention on a matter.';
                    $hasLink = ! empty($notification->data['url']);
                @endphp

                <form method="POST" action="{{ route('notifications.read', $notification->getKey()) }}" class="list-group-item p-0">
                    @csrf
                    <button type="submit" @class(['btn btn-link text-start text-decoration-none d-flex align-items-start gap-3 w-100 px-3 py-3 rounded-0', 'fw-bold' => $notification->unread()])>
                        <span class="mt-1 flex-shrink-0" style="width: .5rem; height: .5rem; border-radius: 50%; background: {{ $notification->unread() ? 'var(--tblr-primary)' : 'transparent' }};"></span>
                        <span class="flex-fill text-body">
                            {{ $message }}
                            <span class="d-block text-meta fw-normal">{{ $notification->created_at->diffForHumans() }}@unless ($hasLink) · no page to open @endunless</span>
                        </span>
                    </button>
                </form>
            @empty
                <p class="text-center text-secondary py-4 mb-0">{{ $show === 'unread' ? 'Nothing unread.' : 'Nothing has needed your attention yet.' }}</p>
            @endforelse
        </div>

        @if ($notifications->hasPages())
            <div class="card-footer d-flex align-items-center justify-content-between">
                <span class="text-meta">showing {{ $notifications->firstItem() }}–{{ $notifications->lastItem() }} of {{ $notifications->total() }}</span>
                <div class="d-flex gap-3">
                    @if (! $notifications->onFirstPage())
                        <a href="{{ $notifications->previousPageUrl() }}" class="text-meta">← newer</a>
                    @endif
                    @if ($notifications->hasMorePages())
                        <a href="{{ $notifications->nextPageUrl() }}" class="text-meta">older →</a>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
