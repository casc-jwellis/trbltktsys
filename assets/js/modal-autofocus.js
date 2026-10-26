/**
 * Bootstrap ignores the autofocus attribute inside a modal, so focus the first
 * [autofocus] field ourselves once the modal has finished opening.
 */
document.addEventListener('shown.bs.modal', function (event) {
    var field = event.target.querySelector('[autofocus]');
    if (field) {
        field.focus();
    }
});
