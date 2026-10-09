import TomSelect from 'tom-select';

// After a failed submit the server re-renders the form with aria-invalid on
// each offending field; send the keyboard to the first one.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('[aria-invalid="true"]')?.focus();
});

const escapeHtml = (text) => {
    const span = document.createElement('span');
    span.textContent = text ?? '';
    return span.innerHTML;
};

// Tom Select pickers (components/tag-select.blade.php for Tags,
// components/user-select.blade.php for Users, components/filter-select.blade.php
// for listing filters): choose an existing option or,
// for Tags, create one by typing. Keeps the original <select>, so the form still
// submits `name`; Tom Select only replaces the control.
document.querySelectorAll('select[data-tag-select]').forEach((select) => {
    const invalid = select.getAttribute('aria-invalid') === 'true';
    const describedBy = select.getAttribute('aria-describedby');

    new TomSelect(select, {
        create: !('noCreate' in select.dataset),
        maxItems: select.multiple ? null : 1,
        plugins: select.multiple ? ['remove_button'] : ('clearable' in select.dataset ? ['clear_button'] : []),
        // Close right after picking, like a native select. Multi pickers stay
        // open so several values can be chosen in a row.
        closeAfterSelect: !select.multiple,
        // Drop the focus too, so the typing input stops taking width from the
        // selected label.
        onItemAdd() {
            if (!select.multiple) this.blur();
        },
        // Tom Select's own texts are English; take them from the page locale.
        render: {
            no_results: () => `<div class="no-results">${escapeHtml(document.body.dataset.tsNoResults)}</div>`,
            option_create: (data, escape) =>
                `<div class="create">${escapeHtml(document.body.dataset.tsCreate)} <strong>${escape(data.input)}</strong>&hellip;</div>`,
        },
        persist: false,
        createOnBlur: false,
        placeholder: select.dataset.placeholder,
        // Tom Select drops attributes from the hidden select; carry the
        // accessible name and error state over to the typing input.
        onInitialize() {
            const input = this.control_input;
            input.setAttribute('aria-label', select.getAttribute('aria-label'));
            if (invalid) {
                input.setAttribute('aria-invalid', 'true');
                if (describedBy) input.setAttribute('aria-describedby', describedBy);
                this.wrapper.classList.add('ts-invalid');
                // Only the visible control should carry the flag, so the
                // focus-first-error handler above lands on it.
                select.removeAttribute('aria-invalid');
            }
        },
    });
});

// First access with no timezone stored: send the browser's IANA timezone once
// so dates render in the User's own timezone from the next page on.
const detectUrl = document.body.dataset.detectTimezone;
if (detectUrl) {
    fetch(detectUrl, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({ timezone: Intl.DateTimeFormat().resolvedOptions().timeZone }),
    }).catch(() => {});
}
