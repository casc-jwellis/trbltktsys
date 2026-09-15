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
        select.value = '';
        textarea.focus();
    });
});
