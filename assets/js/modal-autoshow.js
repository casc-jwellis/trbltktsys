/**
 * Opens any modal marked data-show-on-load as soon as the page loads -- for a
 * modal form the server wants redisplayed with its validation errors after a
 * failed submit, instead of leaving the user to find and reopen it.
 */
(function () {
    document.querySelectorAll('.modal[data-show-on-load]').forEach(function (modal) {
        bootstrap.Modal.getOrCreateInstance(modal).show();
    });
})();
