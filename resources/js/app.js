// After a failed submit the server re-renders the form with aria-invalid on
// each offending field; send the keyboard to the first one.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('[aria-invalid="true"]')?.focus();
});
