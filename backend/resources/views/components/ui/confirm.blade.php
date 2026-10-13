{{--
    Usage:

        <button data-confirm="Remove Sipho from this project?"
                data-confirm-title="Remove from project"
                data-confirm-action="Remove"
                data-confirm-tone="danger"
                data-confirm-icon="user-minus">

    The icon is a Tabler icon name. Left out, it follows the tone: a warning
    triangle for danger, a question mark otherwise.

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
        <div class="modal-dialog modal-dialog-centered" style="--tblr-modal-width: 28rem;" role="document">
            <div class="modal-content">
                <div class="modal-body p-4 pb-3">
                    <span class="avatar avatar-md rounded mb-3"
                          x-bind:class="tone === 'danger' ? 'bg-danger-lt' : 'bg-primary-lt'">
                        <i class="ti" x-bind:class="`ti-${icon}`"></i>
                    </span>

                    <h3 class="fw-semibold mb-1" style="font-family: var(--cms-font-sans);" x-text="title"></h3>
                    <p class="text-secondary mb-0" x-text="message"></p>

                    <div class="mt-3" x-show="promptLabel" x-cloak>
                        <label class="form-label" for="confirm-reason" x-text="promptLabel"></label>
                        <textarea class="form-control" id="confirm-reason" rows="2" x-ref="reason"
                                  x-model="reason" x-on:keydown.enter.meta="proceed()"></textarea>
                    </div>
                </div>

                <div class="modal-footer border-0 bg-transparent d-flex justify-content-end gap-2 p-4 pt-2">
                    <button type="button" class="btn btn-outline-secondary" x-on:click="cancel()" x-text="cancelLabel"></button>

                    <button type="button" class="btn" x-ref="confirm"
                        x-bind:class="tone === 'danger' ? 'btn-danger' : 'btn-primary'"
                        x-bind:disabled="! canProceed"
                        x-on:click="proceed()" x-text="actionLabel"></button>
                </div>
            </div>
        </div>
    </div>
</div>
