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
