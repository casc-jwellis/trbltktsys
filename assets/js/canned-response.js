/** Inserts a canned response's text into the reply textarea when picked from the dropdown. */
document.addEventListener('DOMContentLoaded', function () {
    const select = document.getElementById('canned_response');
    const textarea = document.getElementById('response');
    if (!select || !textarea) {
        return;
    }

    select.addEventListener('change', function () {
        if (!select.value) {
            return;
        }
        textarea.value = select.value;
        // Programmatic changes don't fire "input", so tell autogrow-textarea.js to resize.
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        select.value = '';
        textarea.focus();
    });
});
