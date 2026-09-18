/**
 * The User Information modal on ticket.php opens read-only -- clicking Edit
 * switches its fields to editable in place (Bootstrap's .form-control-plaintext
 * makes a readonly input look like plain text until then). Cancelling or
 * closing the modal reverts to read-only and discards any unsaved changes.
 */
(function () {
    "use strict";

    var modal = document.getElementById('requesterInfoModal');
    if (!modal) {
        return;
    }

    var form = document.getElementById('requesterInfoForm');
    var fields = modal.querySelectorAll('.requester-field');
    var hint = modal.querySelector('.requester-edit-hint');
    var viewButtons = modal.querySelectorAll('.requester-view-only');
    var editButtons = modal.querySelectorAll('.requester-edit-only');
    var editToggle = document.getElementById('requesterEditToggle');
    var cancelEdit = document.getElementById('requesterCancelEdit');

    function setEditing(editing) {
        fields.forEach(function (field) {
            field.readOnly = !editing;
            field.classList.toggle('form-control-plaintext', !editing);
        });
        if (hint) {
            hint.hidden = !editing;
        }
        viewButtons.forEach(function (btn) { btn.hidden = editing; });
        editButtons.forEach(function (btn) { btn.hidden = !editing; });
        if (editing && fields.length) {
            fields[0].focus();
        }
    }

    editToggle.addEventListener('click', function () {
        setEditing(true);
    });

    cancelEdit.addEventListener('click', function () {
        form.reset();
        setEditing(false);
    });

    // Reopening later should always start read-only, whether it was left
    // mid-edit or just closed normally.
    modal.addEventListener('hidden.bs.modal', function () {
        form.reset();
        setEditing(false);
    });

    setEditing(false);
})();
