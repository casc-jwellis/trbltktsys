<div class="card border-danger mb-4">
    <div class="card-header bg-danger text-white">Flush Ticket Data</div>
    <div class="card-body p-4">
        <h2 class="h5">Delete All Tickets</h2>
        <p class="text-body-secondary">
            This permanently deletes every ticket, its conversation thread, its group assignment, any
            attached screenshots, and the requester directory. Staff accounts, groups, categories, canned
            responses, and email settings are left untouched. <strong>This cannot be undone.</strong>
        </p>
        <form method="post" class="mt-4" style="max-width: 480px;"
              onsubmit="return confirm('This will permanently delete ALL tickets and related data. Are you absolutely sure?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="flush_tickets">
            <div class="mb-3">
                <label class="form-label" for="flush_confirmation">
                    Type <code>DELETE ALL TICKETS</code> to confirm
                </label>
                <input class="form-control" id="flush_confirmation" name="confirmation" autocomplete="off" required>
            </div>
            <button type="submit" class="btn btn-danger">Delete All Tickets</button>
        </form>
    </div>
</div>

<div class="card border-danger">
    <div class="card-header bg-danger text-white">Danger Zone</div>
    <div class="card-body p-4">
        <h2 class="h5">Purge All Data</h2>
        <p class="text-body-secondary">
            This permanently deletes every ticket, staff account, and group from the database.
            You will be signed out immediately and sent through initial setup to create a new
            administrator account. <strong>This cannot be undone.</strong>
        </p>
        <form method="post" class="mt-4" style="max-width: 480px;"
              onsubmit="return confirm('This will permanently delete ALL tickets, users, and groups. Are you absolutely sure?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="purge_database">
            <div class="mb-3">
                <label class="form-label" for="purge_confirmation">
                    Type <code>DELETE EVERYTHING</code> to confirm
                </label>
                <input class="form-control" id="purge_confirmation" name="confirmation" autocomplete="off" required>
            </div>
            <button type="submit" class="btn btn-danger">Purge All Data</button>
        </form>
    </div>
</div>
