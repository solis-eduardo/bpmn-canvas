//

// Searchable user <select>s (components/user-select.blade.php): the filter
// input narrows the select's options; the placeholder option always stays.
document.querySelectorAll('[data-user-select]').forEach((root) => {
    const filter = root.querySelector('[data-user-select-filter]');
    const select = root.querySelector('select');
    const options = Array.from(select.options).slice(1);

    filter.addEventListener('input', () => {
        const term = filter.value.trim().toLowerCase();
        const matches = options.filter((option) => option.text.toLowerCase().includes(term));

        select.replaceChildren(select.options[0], ...matches);

        if (term !== '' && matches.length === 1) {
            select.value = matches[0].value;
        } else if (!matches.some((option) => option.value === select.value)) {
            select.value = '';
        }
    });
});
