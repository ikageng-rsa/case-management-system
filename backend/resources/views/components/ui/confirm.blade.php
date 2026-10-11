{{--
    Usage:

        <button data-confirm="Remove Sipho from this project?"
                data-confirm-title="Remove from project"
                data-confirm-action="Remove"
                data-confirm-tone="danger">

    Intercepts the action of a button or link to show a confirm dialogue

    An action that has to be explained asks for the reason here and puts it in a
    field the form already carries:

        <form data-confirm-prompt-label="Why is it being voided?"
              data-confirm-prompt-field="reason">
            <input type="hidden" name="reason">

    The reason is still validated on the server
--}}
<div x-data="confirmDialog" x-on:confirm-ask.window="ask($event.detail)">
    <div class="modal-backdrop" x-show="isOpen" x-cloak x-transition.opacity></div>

    <div class="modal modal-blur confirm-modal" x-show="isOpen" x-cloak x-transition
         x-on:click="$event.target === $el && cancel()"
         x-on:keydown.escape.window="cancel()"
         role="alertdialog" aria-modal="true" x-bind:aria-label="title" tabindex="-1">
        <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-body py-4">
                    <h3 class="mb-2" x-text="title"></h3>
                    <p class="text-secondary mb-0" x-text="message"></p>

                    <div class="mt-3" x-show="promptLabel" x-cloak>
                        <label class="form-label" for="confirm-reason" x-text="promptLabel"></label>
                        <textarea class="form-control" id="confirm-reason" rows="2" x-ref="reason"
                                  x-model="reason" x-on:keydown.enter.meta="proceed()"></textarea>
                    </div>
                </div>


                <div class="modal-footer d-flex align-items-center justify-content-between">
                    <button type="button" class="btn btn-ghost-secondary" x-on:click="cancel()" x-text="cancelLabel"></button>

                    <button type="button" class="btn" x-ref="confirm"
                        x-bind:class="tone === 'danger' ? 'btn-danger' : 'btn-primary'"
                        x-bind:disabled="! canProceed"
                        x-on:click="proceed()" x-text="actionLabel"></button>
                </div>
            </div>
        </div>
    </div>
</div>
