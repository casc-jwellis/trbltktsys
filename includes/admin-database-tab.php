<?php /** @var array $pendingMigrations */ ?>
<div class="card mb-4">
    <div class="card-header">Database Updates</div>
    <div class="card-body p-4">
        <?php if (!$pendingMigrations): ?>
            <p class="mb-0">The database is up to date.</p>
        <?php else: ?>
            <p>Pulled new code? These updates change the database structure and haven't been applied yet:</p>
            <ul>
                <?php foreach ($pendingMigrations as $migration): ?>
                    <li><code><?= e($migration['file']) ?></code> &mdash; <?= e($migration['label']) ?></li>
                <?php endforeach; ?>
            </ul>
            <p class="text-body-secondary small">
                Updates only add or widen columns and tables &mdash; they don't delete data &mdash; but a database
                backup first is a good habit, since a schema change can't be undone.
            </p>
            <form method="post" data-loading-text="Updating...">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="run_migrations">
                <button type="submit" class="btn btn-primary">Run Database Updates</button>
            </form>
        <?php endif; ?>
    </div>
</div>

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
