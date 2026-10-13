@props([
    'masked',
    'target',
    'client',
    'canReveal' => false,
    'seconds' => 10,
])

{{--
    A masked value with an eye beside it. Pressing it fetches the value in full,
    which the server allows only to users cleared to see unmasked data and writes
    to the audit log; it hides itself again after a few seconds.
--}}
@if ($canReveal)
    <span class="d-inline-flex align-items-center gap-1"
          x-data="reveal({{ \Illuminate\Support\Js::from(['url' => route('client.reveal', $client), 'target' => $target, 'masked' => $masked, 'seconds' => $seconds]) }})">
        <span x-text="shown ? value : masked">{{ $masked }}</span>
        <button type="button" class="btn btn-link p-0 text-secondary lh-1" x-on:click="toggle()" x-bind:disabled="loading"
                x-bind:aria-label="shown ? 'Hide' : 'Show in full'" x-bind:title="shown ? 'Hide' : 'Show in full'">
            <i class="ti" x-bind:class="shown ? 'ti-eye-off' : 'ti-eye'"></i>
        </button>
    </span>
@else
    <span>{{ $masked }}</span>
@endif
