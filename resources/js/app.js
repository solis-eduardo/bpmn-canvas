import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.default.css';

// After a failed submit the server re-renders the form with aria-invalid on
// each offending field; send the keyboard to the first one.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('[aria-invalid="true"]')?.focus();
});

// Searchable user <select>s (components/user-select.blade.php): the filter
// input narrows the select's options; the placeholder option always stays.
document.querySelectorAll('[data-user-select]').forEach((root) => {
    const filter = root.querySelector('[data-user-select-filter]');
    const select = root.querySelector('select');
    // The first option is the placeholder; it is never filtered out.
    const options = Array.from(select.options).slice(1);

    filter.addEventListener('input', () => {
        const term = filter.value.trim().toLowerCase();
        const matches = options.filter((option) => option.text.toLowerCase().includes(term));

        select.replaceChildren(select.options[0], ...matches);

        if (!matches.some((option) => option.value === select.value)) {
            select.value = '';
        }
    });
});

// Tag pickers (components/tag-select.blade.php): choose an existing Tag or
// create one by typing. Keeps the original <select>, so the form still
// submits `name`; Tom Select only replaces the control.
document.querySelectorAll('select[data-tag-select]').forEach((select) => {
    const invalid = select.getAttribute('aria-invalid') === 'true';
    const describedBy = select.getAttribute('aria-describedby');

    new TomSelect(select, {
        create: true,
        maxItems: 1,
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
