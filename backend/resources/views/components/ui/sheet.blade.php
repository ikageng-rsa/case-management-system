@props([
    'name',
    'title',
    'lede' => null,
    'icon' => 'plus',
    'accent' => null,
    'bag' => 'default',
    'bags' => [],
    'wide' => false,
    'width' => null,
    'record' => [],
    'action' => null,
    'method' => 'POST',
    'enctype' => null,
    'formData' => null,
])

@php($anyBagHasErrors = collect(array_merge([$bag], $bags))->contains(fn ($b) => $errors->getBag($b)->any()))

{{--
    Usage:
    Open a blank one with   $dispatch('sheet-open', 'add-rate')
    or one filled in with   $dispatch('sheet-open', { name: 'edit-member', record: {…} })

    The buttons of a sheet live in its footer slot, pinned under the body.

    Given an action, the sheet is one form around its body and footer, so a
    submit button in the footer submits what is in the body. formData is an
    Alpine x-data expression for that form, shared by body and footer alike.

    The form can ask before it submits: data-confirm attributes given to the
    sheet are passed on to it, and x-bind:data-confirm makes the question depend
    on what has been filled in (an empty value means no question).

--}}
<div x-data="{
        name: '{{ $name }}',
        record: @js($record),
        panel: null,
        init() {
            this.panel = window.bootstrap.Offcanvas.getOrCreateInstance(this.$refs.panel);
            @if ($anyBagHasErrors) this.open(this.record); @endif
        },
        open(record) {
            this.record = record ?? {};
            this.panel.show();
            this.$nextTick(() => this.$refs.panel?.querySelector('input, select, textarea')?.focus());
        },
        close() { this.panel.hide() },
     }"
     x-on:sheet-open.window="
        if ($event.detail === name) open({});
        else if ($event.detail?.name === name) open($event.detail.record);
     "
     data-sheet="{{ $name }}">

    <div class="offcanvas offcanvas-end" x-ref="panel" tabindex="-1"
         style="--tblr-offcanvas-width: {{ $width ?? ($wide ? '640px' : '420px') }};"
         data-bs-keyboard="false"
         x-on:keydown.escape.window="$store.dialog.open || close()"
         aria-label="{{ $title }}">

        <div class="offcanvas-header border-bottom">
            @isset($header)
                {{ $header }}
            @else
                <span class="avatar avatar-rounded me-2"
                      style="background-color: color-mix(in srgb, {{ $accent ?? 'var(--tblr-primary)' }} 16%, transparent); color: {{ $accent ?? 'var(--tblr-primary)' }};">
                    <x-ui.icon :name="$icon" />
                </span>

                <span class="min-w-0">
                    <h2 class="offcanvas-title">{{ $title }}</h2>
                    @if ($lede)
                        <p class="text-secondary mb-0">{{ $lede }}</p>
                    @endif
                </span>
            @endisset

            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>

        @if ($action)
            <form method="{{ strtoupper($method) === 'GET' ? 'GET' : 'POST' }}" action="{{ $action }}"
                  @if ($enctype) enctype="{{ $enctype }}" @endif
                  @if ($formData) x-data="{{ $formData }}" @endif
                  {{ $attributes->whereStartsWith(['data-confirm', 'x-bind:data-confirm']) }}
                  class="d-flex flex-column flex-grow-1 overflow-hidden" novalidate>
                @csrf
                @if (! in_array(strtoupper($method), ['GET', 'POST'], true))
                    @method($method)
                @endif

                <div class="offcanvas-body">
                    {{ $slot }}
                </div>

                @isset($footer)
                    <div class="border-top p-3">
                        {{ $footer }}
                    </div>
                @endisset
            </form>
        @else
            <div class="offcanvas-body">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="border-top p-3">
                    {{ $footer }}
                </div>
            @endisset
        @endif
    </div>
</div>
