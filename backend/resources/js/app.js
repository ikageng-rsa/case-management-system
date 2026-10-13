// Tabler's bundle, which includes the Bootstrap JS its components are built on.
import { Offcanvas } from '@tabler/core';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

// Sheets drive this directly rather than through data-bs-* attributes, since
// opening one also has to carry a record payload.
window.bootstrap = { Offcanvas };

Alpine.plugin(collapse);
window.Alpine = Alpine;

/* Confirmation dialogue config for the whole app */
Alpine.store('dialog', { open: false });

Alpine.data('confirmDialog', () => ({
    isOpen: false,
    title: 'Are you sure?',
    message: '',
    actionLabel: 'Confirm',
    cancelLabel: 'Cancel',
    tone: 'danger',
    icon: 'alert-triangle',
    promptLabel: null,
    reason: '',
    resolve: null,

    ask(options) {
        this.title = options.title ?? 'Are you sure?';
        this.message = options.message ?? '';
        this.actionLabel = options.actionLabel ?? 'Confirm';
        this.cancelLabel = options.cancelLabel ?? 'Cancel';
        this.tone = options.tone ?? 'danger';
        this.icon = options.icon ?? (this.tone === 'danger' ? 'alert-triangle' : 'help-circle');
        this.promptLabel = options.promptLabel ?? null;
        this.reason = '';
        this.resolve = options.resolve ?? null;
        this.isOpen = true;
        Alpine.store('dialog').open = true;

        // With a reason to give, the field takes the focus; otherwise the safe
        // choice keeps it, so a stray Enter cannot confirm.
        this.$nextTick(() => (this.promptLabel ? this.$refs.reason : this.$refs.confirm)?.focus());
    },

    get canProceed() {
        return this.promptLabel === null || this.reason.trim() !== '';
    },

    settle(answer) {
        this.isOpen = false;
        Alpine.store('dialog').open = false;
        const resolve = this.resolve;
        this.resolve = null;
        resolve?.(answer);
    },

    proceed() {
        if (! this.canProceed) {
            return;
        }

        this.settle(this.promptLabel === null ? true : { reason: this.reason.trim() });
    },

    cancel() {
        this.settle(false);
    },
}));


window.confirmAction = (options = {}) => new Promise((resolve) => {
    window.dispatchEvent(new CustomEvent('confirm-ask', { detail: { ...options, resolve } }));
});

/**
 * Anything carrying data-confirm asks before it acts. Submits and clicks are
 * caught during the capture phase so nothing else has run by the time we stop
 * them, and the original action is replayed only on a yes.
 */
const confirmOptionsFrom = (element) => ({
    message: element.dataset.confirm,
    title: element.dataset.confirmTitle,
    actionLabel: element.dataset.confirmAction,
    cancelLabel: element.dataset.confirmCancel,
    tone: element.dataset.confirmTone,
    icon: element.dataset.confirmIcon,
    promptLabel: element.dataset.confirmPromptLabel,
});

/*
 * An action that must be explained — voiding an invoice, writing off a balance —
 * asks for the reason in the dialogue and carries it back on the form. The field
 * it fills has to already exist in the markup, so the request is the same shape
 * whether it was typed here or posted without JavaScript at all.
 */
const applyAnswerTo = (form, answer) => {
    const field = form.dataset.confirmPromptLabel ? form.elements[form.dataset.confirmPromptField] : null;

    if (field && answer?.reason) {
        field.value = answer.reason;
    }
};

document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-confirm]');

    if (! form || form.dataset.confirmed === 'yes') {
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    window.confirmAction(confirmOptionsFrom(form)).then((answer) => {
        if (answer) {
            applyAnswerTo(form, answer);
            form.dataset.confirmed = 'yes';
            form.requestSubmit();
        }
    });
}, true);

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-confirm]');

    if (! trigger || trigger.tagName === 'FORM' || trigger.dataset.confirmed === 'yes') {
        return;
    }

    // A submit button inside a form is the form's business, handled above.
    if (trigger.form && trigger.type === 'submit') {
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    window.confirmAction(confirmOptionsFrom(trigger)).then((answer) => {
        if (answer) {
            trigger.dataset.confirmed = 'yes';
            trigger.click();
        }
    });
}, true);


/*
 * The client intake form: which identity fields apply to the kind of client,
 * and whether the matter being opened alongside is before a court.
 */
Alpine.data('clientIntake', ({ type = 'individual', matterTypeId = '', matterTypes = {} } = {}) => ({
    type,
    matterTypeId,
    matterTypes,

    get litigation() {
        return this.matterTypes[this.matterTypeId]?.litigation ?? false;
    },
}));

/*
 * The steps of opening a matter, walked client side and submitted as one
 * ordinary form. The server validates every field again, so a step cannot be
 * skipped by going around the page.
 *
 * Where the client is already known (opening from their page) the step for
 * choosing one is left out.
 */
Alpine.data('matterIntake', ({
    step = 1,
    client = null,
    fixedClient = false,
    types = {},
    typeId = '',
    urls = {},
} = {}) => ({
    step,
    client,
    fixedClient,
    types,
    typeId,
    urls,

    query: '',
    results: [],
    searching: false,
    searched: false,
    clientMissing: false,
    lookups: 0,

    review: {},
    reference: null,

    get steps() {
        return this.fixedClient ? ['Matter', 'Review'] : ['Client', 'Matter', 'Review'];
    },

    get current() {
        return this.steps[this.step - 1];
    },

    get litigation() {
        return this.types[this.typeId]?.litigation ?? false;
    },

    async search() {
        const term = this.query.trim();
        const lookup = ++this.lookups;

        if (term.length < 2) {
            this.results = [];
            this.searched = false;

            return;
        }

        this.searching = true;

        try {
            const response = await fetch(`${this.urls.lookup}?q=${encodeURIComponent(term)}`, {
                headers: { Accept: 'application/json' },
            });

            // Only the latest search may answer.
            if (lookup === this.lookups && response.ok) {
                this.results = await response.json();
                this.searched = true;
            }
        } finally {
            if (lookup === this.lookups) {
                this.searching = false;
            }
        }
    },

    choose(result) {
        this.client = result;
        this.clientMissing = false;
        this.results = [];
        this.query = '';
        this.searched = false;
    },

    clear() {
        this.client = null;
    },

    next() {
        if (this.current === 'Client' && this.client === null) {
            this.clientMissing = true;

            return;
        }

        const fields = this.$refs['step' + this.step].querySelectorAll('input, select, textarea');

        for (const field of fields) {
            if (! field.reportValidity()) {
                return;
            }
        }

        this.step++;

        if (this.current === 'Review') {
            this.summarise();
        }
    },

    back() {
        this.step--;
    },

    summarise() {
        const value = (ref) => this.$refs[ref]?.value || '—';
        const text = (ref) => this.$refs[ref]?.selectedOptions[0]?.text || '—';

        this.review = {
            type: this.types[this.typeId]?.name ?? '—',
            court: this.litigation ? text('court') : 'Not before a court',
            title: value('title'),
            instructed: value('instructed'),
            attorney: this.$refs.attorney?.value ? text('attorney') : 'Not assigned yet',
            narration: this.$refs.narration?.value || '—',
        };

        this.previewReference();
    },

    // The sequence is only reserved when the matter is opened, so this is a preview.
    async previewReference() {
        this.reference = null;

        const params = new URLSearchParams({
            client_id: this.client.id,
            matter_type_id: this.typeId,
            instructed_at: this.$refs.instructed?.value ?? '',
        });

        try {
            const response = await fetch(`${this.urls.reference}?${params}`, { headers: { Accept: 'application/json' } });

            if (response.ok) {
                this.reference = (await response.json()).reference;
            }
        } catch {
            this.reference = null;
        }
    },
}));


/*
 * A masked value that can be shown in full, briefly. The value is asked for from
 * the server, which allows it only to those cleared to see it and records that
 * it was read; it is held here for a few seconds and then dropped, so it does
 * not linger on the page.
 */
Alpine.data('reveal', ({ url, target, masked, seconds = 10 }) => ({
    masked,
    value: null,
    shown: false,
    loading: false,
    timer: null,

    async toggle() {
        if (this.shown) {
            this.hide();

            return;
        }

        this.loading = true;

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ target }),
            });

            if (! response.ok) {
                throw new Error('Not allowed');
            }

            this.value = (await response.json()).value;
            this.shown = true;
            this.timer = setTimeout(() => this.hide(), seconds * 1000);
        } catch {
            window.toast({ message: 'That could not be shown.', tone: 'danger' });
        } finally {
            this.loading = false;
        }
    },

    hide() {
        clearTimeout(this.timer);
        this.value = null;
        this.shown = false;
    },
}));


/* Floating messages config */
Alpine.data('toasts', (seed = []) => ({
    items: [],
    nextId: 1,

    init() {
        seed.forEach((toast) => this.push(toast));
    },

    push({ message, tone = 'success', life = null }) {
        if (! message) {
            return;
        }

        const id = this.nextId++;

        this.items.push({ id, message, tone });

        const timeout = life ?? (tone === 'success' ? 5000 : null);

        if (timeout) {
            setTimeout(() => this.dismiss(id), timeout);
        }
    },

    dismiss(id) {
        this.items = this.items.filter((toast) => toast.id !== id);
    },
}));

window.toast = (options = {}) => window.dispatchEvent(new CustomEvent('toast', { detail: options }));

Alpine.start();
