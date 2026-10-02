/**
 * Reloads the page without a keyword filter when the search box's clear (x)
 * button is clicked, instead of leaving the stale results up until the user
 * presses Enter. Opt in per-input via data-submit-on-clear. Only fires when
 * the page was loaded with a search term (the input's saved value attribute),
 * so emptying a box that wasn't filtering anything doesn't reload the page.
 */
(function () {
    document.querySelectorAll('input[type="search"][data-submit-on-clear]').forEach(function (input) {
        // "search" fires on Enter and on the clear button/Escape; only the
        // latter leaves the box empty.
        input.addEventListener('search', function () {
            if (input.value === '' && input.defaultValue !== '' && input.form) {
                input.form.submit();
            }
        });
    });
})();
