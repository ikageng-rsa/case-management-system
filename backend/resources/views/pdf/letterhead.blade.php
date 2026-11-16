@if ($letterhead)
    {{-- The firm's own letterhead, in place of its details set as text. --}}
    <img src="{{ $letterhead }}" alt="" class="letterhead">
@else
    <div class="{{ $theme === 'modern' ? 'band' : '' }}">
        <div class="firm">{{ $firm->name }}</div>
        @foreach ($firm->addressLines as $line)
            <div class="meta">{{ $line }}</div>
        @endforeach
        @if ($firm->phone || $firm->email)
            <div class="meta">{{ collect([$firm->phone, $firm->email])->filter()->implode(' · ') }}</div>
        @endif
        @if ($firm->website)
            <div class="meta">{{ $firm->website }}</div>
        @endif
        @if ($firm->registrationNumber)
            <div class="meta">Company reg. {{ $firm->registrationNumber }}</div>
        @endif
    </div>
@endif
