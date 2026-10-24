{{--
    The one place the app says how something went.

    Rendered once in the layout, so every screen gets it without asking. Messages
    arrive two ways: flashed to the session by a redirect, and dispatched at
    runtime with window.toast({ message, tone }).

    A success announces itself and gets out of the way. A problem does not:
    it stays until it is dismissed, because someone reading an error is usually
    part-way through fixing the field it is about.
--}}
@php
    $seed = collect();

    if (session('status')) {
        $seed->push(['tone' => 'success', 'message' => session('status')]);
    }

    // Errors may sit in a named bag when a sheet has to reopen on its own fields.
    /** 
    collect($errors->getBags())
        ->flatMap(fn ($bag) => $bag->all())
        ->unique()
        ->each(fn ($error) => $seed->push(['tone' => 'danger', 'message' => $error]));
    */
@endphp


<div class="position-fixed top-0 end-0 p-3"
     style="z-index:1090; width: max-content; max-width: 100%;" x-data="toasts(@js($seed->values()))"
     x-on:toast.window="push($event.detail)"
     role="status" aria-live="polite">

    <template x-for="toast in items" :key="toast.id">    
        
    <div class="alert alert-dismissible" x-bind:class="`alert-${toast.tone}`" role="alert">
        
        <div class="alert-icon">
            <i class="ti"
               x-bind:class="toast.tone === 'success' ? 'ti-circle-check' : 'ti-alert-triangle'"></i>
        </div>

        <span x-text="toast.message"></span>

        <button type="button" class="btn-close" x-on:click="dismiss(toast.id)" aria-label="close"></button>
    </div>
        
        <!-- <div class="toast fade show alert-dismissible" x-bind:class="`is-${toast.tone}`" id="toast-simple" role="alert" aria-live="assertive" aria-atomic="true">
            <i class="ti toast-note__icon"
               x-bind:class="toast.tone === 'success' ? 'ti-circle-check' : 'ti-alert-triangle'"></i>

            <span class="toast-note__body" x-text="toast.message"></span>

            <button type="button" class="toast-note__close" x-on:click="dismiss(toast.id)"
                    aria-label="Dismiss">
                <i class="ti ti-x"></i>
            </button>
        </div> -->
    </template>
</div>
