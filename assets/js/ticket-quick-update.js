/**
 * Saves the Status/Priority selects on ticket.php immediately on change, via
 * the small 'quick_update' JSON action in ticket.php -- kept separate from
 * the main Respond & Manage Ticket form so it never touches (or accidentally
 * submits) a response the agent might be mid-way through typing there.
 */
(function () {
    "use strict";

    var statusSelect = document.getElementById('quickStatus');
    var prioritySelect = document.getElementById('quickPriority');
    if (!statusSelect || !prioritySelect) {
        return;
    }

    var indicator = document.getElementById('quickUpdateStatus');
    var csrfInput = document.querySelector('#manageTicketForm input[name="csrf_token"]');
    // The "Respond & Manage Ticket" form below has its own independent Status
    // select that only saves when that form is submitted. Keep it in sync
    // after a successful quick-save here, or a later "Save Changes" click
    // would silently resubmit its stale value and undo this change.
    var formStatusSelect = document.querySelector('#manageTicketForm select[name="status"]');

    function setIndicator(text, isError) {
        if (!indicator) {
            return;
        }
        indicator.textContent = text;
        indicator.classList.toggle('text-danger', !!isError);
        indicator.classList.toggle('text-body-secondary', !isError);
    }

    function save() {
        statusSelect.disabled = true;
        prioritySelect.disabled = true;
        setIndicator('Saving…', false);

        var body = new URLSearchParams({
            action: 'quick_update',
            status: statusSelect.value,
            priority: prioritySelect.value,
            csrf_token: csrfInput ? csrfInput.value : ''
        });

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin'
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.ok) {
                    setIndicator(data.error || 'Failed to save.', true);
                    return;
                }
                setIndicator(data.mailError ? 'Saved (email failed)' : 'Saved', !!data.mailError);
                window.setTimeout(function () { setIndicator('', false); }, 3000);
                if (formStatusSelect) {
                    formStatusSelect.value = statusSelect.value;
                }
            })
            .catch(function () {
                setIndicator('Failed to save -- check your connection.', true);
            })
            .finally(function () {
                statusSelect.disabled = false;
                prioritySelect.disabled = false;
            });
    }

    statusSelect.addEventListener('change', save);
    prioritySelect.addEventListener('change', save);
})();
