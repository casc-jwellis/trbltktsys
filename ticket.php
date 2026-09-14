<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    'SELECT t.*, r.phone AS requester_phone
     FROM tickets t
     LEFT JOIN requesters r ON r.email = t.requester_email
     WHERE t.id = ?'
);
$stmt->execute([$id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $status = (string) ($_POST['status'] ?? '');
        $priority = (string) ($_POST['priority'] ?? '');
        $internalNotes = trim((string) ($_POST['internal_notes'] ?? ''));
        $userIds = valid_ids_from_post($_POST['assigned_users'] ?? [], assignable_users(ticket_assigned_user_ids($id)));
        $groupIds = valid_ids_from_post($_POST['assigned_groups'] ?? [], assignable_groups());

        if (!in_array($status, TICKET_STATUSES, true) || !in_array($priority, TICKET_PRIORITIES, true)) {
            $error = 'Please choose valid values.';
        } else {
            db()->beginTransaction();

            $stmt = db()->prepare(
                'UPDATE tickets SET status = ?, priority = ?, internal_notes = ? WHERE id = ?'
            );
            $stmt->execute([$status, $priority, $internalNotes, $id]);
            save_ticket_assignments($id, $userIds, $groupIds);

            db()->commit();

            flash('success', 'Ticket #' . $id . ' updated.');
            header('Location: ticket.php?id=' . $id);
            exit;
        }
    }
}

$assignedUserIds = ticket_assigned_user_ids($id);
$assignedGroupIds = ticket_assigned_group_ids($id);
$agents = assignable_users($assignedUserIds);
$groups = assignable_groups();

$pageTitle = 'Ticket #' . $id;
require __DIR__ . '/includes/header.php';
?>

<div class="mb-4">
    <a href="dashboard.php" class="text-decoration-none">&larr; Back to Ticket Queue</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Ticket #<?= (int) $ticket['id'] ?></div>
            <div class="card-body p-4">
                <h1 class="h4"><?= e($ticket['subject']) ?></h1>
                <p class="text-body-secondary mb-4">
                    Submitted by <?= e($ticket['requester_name']) ?>
                    (<a href="mailto:<?= e($ticket['requester_email']) ?>"><?= e($ticket['requester_email']) ?></a><?php if (!empty($ticket['requester_phone'])): ?>,
                    <a href="tel:<?= e($ticket['requester_phone']) ?>"><?= e($ticket['requester_phone']) ?></a><?php endif; ?>)
                    on <?= e(date('M j, Y g:i A', strtotime($ticket['created_at']))) ?>
                </p>
                <p style="white-space: pre-wrap;"><?= e($ticket['description']) ?></p>
                <?php if (!empty($ticket['attachment_path'])): ?>
                    <hr>
                    <p class="fw-semibold mb-2">Screenshot</p>
                    <a href="<?= e($ticket['attachment_path']) ?>" target="_blank" rel="noopener">
                        <img src="<?= e($ticket['attachment_path']) ?>" alt="Screenshot attached to ticket #<?= (int) $ticket['id'] ?>" class="img-fluid rounded border" style="max-height: 400px;">
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Manage Ticket</div>
            <div class="card-body p-4">
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <?php foreach (TICKET_STATUSES as $status): ?>
                                <option value="<?= e($status) ?>" <?= $ticket['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="priority">Priority</label>
                        <select class="form-select" id="priority" name="priority">
                            <?php foreach (TICKET_PRIORITIES as $priority): ?>
                                <option value="<?= e($priority) ?>" <?= $ticket['priority'] === $priority ? 'selected' : '' ?>><?= e($priority) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (!ticket_assignments_supported()): ?>
                        <div class="alert alert-warning small">Ticket assignment is unavailable until an administrator visits <a href="migrate.php">migrate.php</a> to update the database.</div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label d-block">Assigned Users</label>
                        <?php if (!$agents): ?>
                            <p class="text-body-secondary small mb-0">No agents available.</p>
                        <?php endif; ?>
                        <?php foreach ($agents as $agent): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="assigned_users[]" value="<?= (int) $agent['id'] ?>" id="agent_<?= (int) $agent['id'] ?>" <?= in_array((int) $agent['id'], $assignedUserIds, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="agent_<?= (int) $agent['id'] ?>"><?= e($agent['full_name']) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label d-block">Assigned Groups</label>
                        <?php if (!$groups): ?>
                            <p class="text-body-secondary small mb-0">No groups available.</p>
                        <?php endif; ?>
                        <?php foreach ($groups as $group): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="assigned_groups[]" value="<?= (int) $group['id'] ?>" id="group_<?= (int) $group['id'] ?>" <?= in_array((int) $group['id'], $assignedGroupIds, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="group_<?= (int) $group['id'] ?>"><?= e($group['name']) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="internal_notes">Internal Notes</label>
                        <textarea class="form-control" id="internal_notes" name="internal_notes" rows="4"><?= e($ticket['internal_notes']) ?></textarea>
                        <div class="form-text">Only visible to helpdesk staff.</div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
