<?php
/** @var array $cannedResponses */
?>

<?php if (!canned_responses_supported()): ?>
    <div class="alert alert-warning">The database is out of date — canned responses are unavailable until an administrator runs the pending updates on the <a href="admin-settings.php?tab=database">Database tab</a>.</div>
<?php else: ?>

<div class="d-flex justify-content-end mb-3">
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#responseModal">+ New Response</button>
</div>

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
                                        data-bs-toggle="modal" data-bs-target="#responseModal"
                                        data-response-id="<?= (int) $response['id'] ?>"
                                        data-response-title="<?= e($response['title']) ?>"
                                        data-response-body="<?= e($response['body']) ?>">
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

<?php // One modal serves both "+ New Response" and every row's Edit button; assets/js/response-modal.js switches it between the two. ?>
<div class="modal fade" id="responseModal" tabindex="-1" aria-labelledby="responseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_canned_response">
                <input type="hidden" name="response_id" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="responseModalLabel">New Canned Response</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="response_title">Title</label>
                        <input class="form-control" id="response_title" name="title" required maxlength="100" placeholder="e.g. Password Reset Instructions">
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="response_body">Response Text</label>
                        <textarea class="form-control" id="response_body" name="body" rows="6" data-autogrow required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" data-response-submit>Create Response</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php endif; ?>
