<?php
/** @var array $cannedResponses */
?>

<?php if (!canned_responses_supported()): ?>
    <div class="alert alert-warning">The database is out of date — canned responses are unavailable until an administrator visits <a href="migrate.php">migrate.php</a>.</div>
<?php else: ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">New Canned Response</div>
            <div class="card-body p-4">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_canned_response">
                    <div class="mb-3">
                        <label class="form-label" for="response_title">Title</label>
                        <input class="form-control" id="response_title" name="title" required maxlength="100" placeholder="e.g. Password Reset Instructions">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="response_body">Response Text</label>
                        <textarea class="form-control" id="response_body" name="body" rows="6" required></textarea>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Create Response</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Preview</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$cannedResponses): ?>
                            <tr><td colspan="3" class="text-center text-body-secondary py-4">No canned responses yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($cannedResponses as $response): ?>
                            <tr>
                                <td><?= e($response['title']) ?></td>
                                <td class="text-body-secondary"><?= e(mb_strimwidth($response['body'], 0, 60, '…')) ?></td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="modal" data-bs-target="#editResponseModal<?= (int) $response['id'] ?>">
                                            Edit
                                        </button>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Remove this canned response? This cannot be undone.');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_canned_response">
                                            <input type="hidden" name="response_id" value="<?= (int) $response['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php foreach ($cannedResponses as $response): ?>
    <div class="modal fade" id="editResponseModal<?= (int) $response['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_canned_response">
                    <input type="hidden" name="response_id" value="<?= (int) $response['id'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Canned Response</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="edit_response_title_<?= (int) $response['id'] ?>">Title</label>
                            <input class="form-control" id="edit_response_title_<?= (int) $response['id'] ?>" name="title" required maxlength="100" value="<?= e($response['title']) ?>">
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="edit_response_body_<?= (int) $response['id'] ?>">Response Text</label>
                            <textarea class="form-control" id="edit_response_body_<?= (int) $response['id'] ?>" name="body" rows="6" required><?= e($response['body']) ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php endif; ?>
