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

if (!is_admin() && !user_can_view_ticket($id, current_user_id())) {
    flash('error', 'You do not have permission to view that ticket.');
    header('Location: dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $action = (string) ($_POST['action'] ?? 'manage_ticket');

        if ($action === 'add_internal_note') {
            $note = trim((string) ($_POST['internal_note'] ?? ''));
            if ($note === '') {
                $error = 'Please enter a note.';
            } else {
                add_ticket_comment($id, current_user_id(), $note, true);
                flash('success', 'Internal note added.');
                header('Location: ticket.php?id=' . $id);
                exit;
            }
        } elseif ($action === 'reassign_ticket') {
            $userIds = valid_ids_from_post($_POST['assigned_users'] ?? [], assignable_users(ticket_assigned_user_ids($id)));
            $groupIds = valid_ids_from_post($_POST['assigned_groups'] ?? [], assignable_groups());
            save_ticket_assignments($id, $userIds, $groupIds);
            flash('success', 'Ticket #' . $id . ' reassigned.');
            header('Location: ticket.php?id=' . $id);
            exit;
        } else {
            $status = (string) ($_POST['status'] ?? '');
            $priority = (string) ($_POST['priority'] ?? '');
            $response = trim((string) ($_POST['response'] ?? ''));

            if (!in_array($status, TICKET_STATUSES, true) || !in_array($priority, TICKET_PRIORITIES, true)) {
                $error = 'Please choose valid values.';
            } else {
                db()->beginTransaction();

                $stmt = db()->prepare('UPDATE tickets SET status = ?, priority = ? WHERE id = ?');
                $stmt->execute([$status, $priority, $id]);
                if ($response !== '') {
                    add_ticket_comment($id, current_user_id(), $response, false);
                }

                db()->commit();

                flash('success', 'Ticket #' . $id . ' updated.');
                header('Location: ticket.php?id=' . $id);
                exit;
            }
        }
    }
}

$assignedUserIds = ticket_assigned_user_ids($id);
$assignedGroupIds = ticket_assigned_group_ids($id);
$agents = assignable_users($assignedUserIds);
$groups = assignable_groups();
$comments = ticket_comments($id);

$assignedUserNames = array_values(array_intersect_key(
    array_column($agents, 'full_name', 'id'),
    array_flip($assignedUserIds)
));
$assignedGroupNames = array_values(array_intersect_key(
    array_column($groups, 'name', 'id'),
    array_flip($assignedGroupIds)
));
$assigneeNames = array_merge($assignedUserNames, $assignedGroupNames);

$assigneeTooltipHtml = '<div class="text-start"><div class="fw-semibold border-bottom pb-1 mb-1">Assigned To</div>';
if (!$assignedUserNames && !$assignedGroupNames) {
    $assigneeTooltipHtml .= '<div>Unassigned</div>';
} else {
    if ($assignedUserNames) {
        $assigneeTooltipHtml .= '<div>Users: ' . e(implode(', ', $assignedUserNames)) . '</div>';
    }
    if ($assignedGroupNames) {
        $assigneeTooltipHtml .= '<div>Groups: ' . e(implode(', ', $assignedGroupNames)) . '</div>';
    }
}
$assigneeTooltipHtml .= '</div>';

$pageTitle = 'Ticket #' . $id;
require __DIR__ . '/includes/header.php';
?>

<div class="mb-4">
    <a href="dashboard.php" class="text-decoration-none">&larr; Back to Ticket Queue</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center gap-2">
        <span>Ticket #<?= (int) $ticket['id'] ?></span>
        <select class="form-select form-select-sm w-auto" name="priority" form="manageTicketForm" aria-label="Priority">
            <?php foreach (TICKET_PRIORITIES as $priority): ?>
                <option value="<?= e($priority) ?>" <?= $ticket['priority'] === $priority ? 'selected' : '' ?>><?= e($priority) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
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

<div class="card mb-4">
    <div class="card-header">Conversation</div>
    <div class="card-body p-4">
        <?php if (!ticket_comments_supported()): ?>
            <div class="alert alert-warning small mb-0">Responses and internal notes are unavailable until an administrator visits <a href="migrate.php">migrate.php</a> to update the database.</div>
        <?php elseif (!$comments): ?>
            <p class="text-body-secondary mb-0">No responses yet.</p>
        <?php else: ?>
            <?php foreach ($comments as $comment): ?>
                <?php $isInternal = (int) $comment['is_internal'] === 1; ?>
                <div class="border-start <?= $isInternal ? 'border-warning' : 'border-primary' ?> border-3 <?= $isInternal ? 'bg-warning-subtle' : 'bg-body-tertiary' ?> rounded p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-start mb-1 gap-2">
                        <span class="badge <?= $isInternal ? 'text-bg-warning' : 'text-bg-primary' ?>"><?= $isInternal ? 'Internal Note' : 'Response to Submitter' ?></span>
                        <small class="text-body-secondary text-nowrap"><?= e(date('M j, Y g:i A', strtotime($comment['created_at']))) ?></small>
                    </div>
                    <p class="mb-1 fw-semibold"><?= e($comment['author_name'] ?? 'Unknown') ?></p>
                    <p class="mb-0" style="white-space: pre-wrap;"><?= e($comment['body']) ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        Respond &amp; Manage Ticket
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#internalNoteModal">+ Add Internal Note</button>
            <?php if (ticket_assignments_supported()): ?>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#reassignModal">Reassign</button>
                <span
                    class="text-body-secondary d-inline-flex"
                    style="cursor: help;"
                    tabindex="0"
                    data-bs-toggle="tooltip"
                    data-bs-html="true"
                    data-bs-placement="bottom"
                    title="<?= e($assigneeTooltipHtml) ?>"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <circle cx="8" cy="8" r="6.5"></circle>
                        <line x1="8" y1="7.25" x2="8" y2="11.25"></line>
                        <circle cx="8" cy="5" r="0.75" fill="currentColor" stroke="none"></circle>
                    </svg>
                    <span class="visually-hidden">Assigned agents</span>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-4">
        <div class="mb-4 pb-4 border-bottom">
            <div class="fw-semibold small text-body-secondary mb-1">Assigned To</div>
            <?php if (!$assigneeNames): ?>
                <span class="text-body-secondary">Unassigned</span>
            <?php else: ?>
                <?php foreach ($assigneeNames as $name): ?>
                    <span class="badge text-bg-secondary me-1"><?= e($name) ?></span>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if (!ticket_assignments_supported()): ?>
            <div class="alert alert-warning small">Ticket assignment is unavailable until an administrator visits <a href="migrate.php">migrate.php</a> to update the database.</div>
        <?php endif; ?>
        <form method="post" id="manageTicketForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="manage_ticket">
            <div class="mb-3">
                <label class="form-label" for="response">Response to Submitter</label>
                <textarea class="form-control" id="response" name="response" rows="4" placeholder="Type a reply the submitter will see..."></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <?php foreach (TICKET_STATUSES as $status): ?>
                        <option value="<?= e($status) ?>" <?= $ticket['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="internalNoteModal" tabindex="-1" aria-labelledby="internalNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_internal_note">
                <div class="modal-header">
                    <h5 class="modal-title" id="internalNoteModalLabel">Add Internal Note</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <textarea class="form-control" name="internal_note" rows="5" placeholder="Only visible to helpdesk staff..." required></textarea>
                    <div class="form-text">Not sent to the submitter — visible only to helpdesk staff.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Add Note</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (ticket_assignments_supported()): ?>
<div class="modal fade" id="reassignModal" tabindex="-1" aria-labelledby="reassignModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reassign_ticket">
                <div class="modal-header">
                    <h5 class="modal-title" id="reassignModalLabel">Reassign Ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4">
                        <label class="form-label">Assigned Users</label>
                        <?php if (!$agents): ?>
                            <p class="text-body-secondary small mb-0">No agents available.</p>
                        <?php else: ?>
                            <div class="assignment-picker">
                                <div class="assignment-pills mb-2"></div>
                                <input type="text" class="form-control form-control-sm assignment-search" placeholder="Search agents...">
                                <div class="list-group assignment-dropdown"></div>
                                <div class="assignment-options">
                                    <?php foreach ($agents as $agent): ?>
                                        <div class="form-check assignment-option">
                                            <input class="form-check-input" type="checkbox" name="assigned_users[]" value="<?= (int) $agent['id'] ?>" id="agent_<?= (int) $agent['id'] ?>" <?= in_array((int) $agent['id'], $assignedUserIds, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="agent_<?= (int) $agent['id'] ?>"><?= e($agent['full_name']) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Assigned Groups</label>
                        <?php if (!$groups): ?>
                            <p class="text-body-secondary small mb-0">No groups available.</p>
                        <?php else: ?>
                            <div class="assignment-picker">
                                <div class="assignment-pills mb-2"></div>
                                <input type="text" class="form-control form-control-sm assignment-search" placeholder="Search groups...">
                                <div class="list-group assignment-dropdown"></div>
                                <div class="assignment-options">
                                    <?php foreach ($groups as $group): ?>
                                        <div class="form-check assignment-option">
                                            <input class="form-check-input" type="checkbox" name="assigned_groups[]" value="<?= (int) $group['id'] ?>" id="group_<?= (int) $group['id'] ?>" <?= in_array((int) $group['id'], $assignedGroupIds, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="group_<?= (int) $group['id'] ?>"><?= e($group['name']) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
