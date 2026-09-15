/**
 * Disables a form's submit button and swaps its label the moment the form
 * is actually submitted (native HTML5 validation already passed), so a slow
 * request -- sending an email via SMTP can take several seconds -- gives
 * immediate feedback and can't be double-submitted. Opt in per-form via
 * data-loading-text="...".
 */
(function () {
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        var loadingText = form.getAttribute('data-loading-text');
        if (!loadingText) {
            return;
        }

        var button = form.querySelector('button[type="submit"]');
        if (!button || button.disabled) {
            return;
        }

        button.disabled = true;
        button.textContent = loadingText;
    });
})();
