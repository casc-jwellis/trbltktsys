/**
 * Admin Settings -> Responses: the single #responseModal is used for both
 * creating and editing a canned response. The button that opened it decides
 * which -- an Edit button carries data-response-id/-title/-body, "+ New
 * Response" carries none.
 */
(function () {
    var modal = document.getElementById('responseModal');
    if (!modal) {
        return;
    }

    var form = modal.querySelector('form');
    var heading = modal.querySelector('.modal-title');
    var submit = modal.querySelector('[data-response-submit]');

    modal.addEventListener('show.bs.modal', function (event) {
        var trigger = event.relatedTarget;
        var editing = trigger && trigger.hasAttribute('data-response-id');

        form.elements.action.value = editing ? 'update_canned_response' : 'create_canned_response';
        form.elements.response_id.value = editing ? trigger.getAttribute('data-response-id') : '';
        form.elements.title.value = editing ? trigger.getAttribute('data-response-title') : '';
        form.elements.body.value = editing ? trigger.getAttribute('data-response-body') : '';
        heading.textContent = editing ? 'Edit Canned Response' : 'New Canned Response';
        submit.textContent = editing ? 'Save Changes' : 'Create Response';
    });

    modal.addEventListener('shown.bs.modal', function () {
        form.elements.title.focus();
    });
})();
