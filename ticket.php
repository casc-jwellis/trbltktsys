<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/mail.php';
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

// Status/priority save immediately on change (assets/js/ticket-quick-update.js)
// rather than waiting for the "Save Changes" button below -- this is its own
// small JSON endpoint, not folded into the manage_ticket action, so it never
// touches (or accidentally submits) whatever draft response the agent might
// be mid-way through typing in that form.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'quick_update') {
    header('Content-Type: application/json');

    if (!verify_csrf()) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Your session expired. Please refresh and try again.']);
        exit;
    }

    $status = (string) ($_POST['status'] ?? '');
    $priority = (string) ($_POST['priority'] ?? '');

    if (!in_array($status, TICKET_STATUSES, true) || !in_array($priority, TICKET_PRIORITIES, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Please choose valid values.']);
        exit;
    }

    // Priority is never shown to the submitter (see includes/mail.php), so
    // only a status change is worth emailing them about -- otherwise every
    // priority tweak while triaging would fire an email about a field they
    // can't even see.
    $statusChanged = $status !== $ticket['status'];

    db()->prepare('UPDATE tickets SET status = ?, priority = ? WHERE id = ?')->execute([$status, $priority, $id]);
    $ticket['status'] = $status;
    $ticket['priority'] = $priority;

    $mailError = null;
    if ($statusChanged && ticket_public_tokens_supported() && !empty($ticket['public_token'])) {
        try {
            send_ticket_update_notification($ticket, '');
        } catch (Throwable $e) {
            $mailError = $e->getMessage();
        }
    }

    echo json_encode(['ok' => true, 'mailError' => $mailError]);
    exit;
}

$stmt = db()->prepare('SELECT name, email, phone FROM requesters WHERE email = ?');
$stmt->execute([$ticket['requester_email']]);
$requester = $stmt->fetch();

$stmt = db()->prepare('SELECT COUNT(*) FROM tickets WHERE requester_email = ?');
$stmt->execute([$ticket['requester_email']]);
$requesterTicketCount = (int) $stmt->fetchColumn();

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
            $groupIds = valid_ids_from_post($_POST['assigned_groups'] ?? [], assignable_groups());
            save_ticket_assignments($id, $groupIds);

            $agentId = (int) ($_POST['assigned_agent_id'] ?? 0);
            $validAgentIds = array_column(active_agents(), 'id');
            save_ticket_assigned_agent($id, in_array($agentId, $validAgentIds, true) ? $agentId : null);

            flash('success', 'Ticket #' . $id . ' reassigned.');
            header('Location: ticket.php?id=' . $id);
            exit;
        } elseif ($action === 'update_requester') {
            $newName = trim((string) ($_POST['name'] ?? ''));
            $newEmail = trim((string) ($_POST['email'] ?? ''));
            $newPhone = trim((string) ($_POST['phone'] ?? ''));

            $reqErrors = [];
            if ($newName === '') {
                $reqErrors[] = 'Please enter a name.';
            }
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $reqErrors[] = 'Please enter a valid email address.';
            }
            if ($newPhone === '') {
                $reqErrors[] = 'Please enter a phone number.';
            } elseif (strlen($newPhone) > 30) {
                $reqErrors[] = 'Phone number is too long (30 characters max).';
            }

            if ($reqErrors) {
                flash('error', implode(' ', $reqErrors));
            } else {
                $oldEmail = $ticket['requester_email'];

                try {
                    db()->beginTransaction();

                    if ($newEmail !== $oldEmail) {
                        try {
                            // Renames the existing requesters row's primary
                            // key -- every ticket referencing it follows
                            // automatically via the ON UPDATE CASCADE
                            // foreign key in schema.sql.
                            db()->prepare('UPDATE requesters SET email = ?, name = ?, phone = ? WHERE email = ?')
                                ->execute([$newEmail, $newName, $newPhone, $oldEmail]);
                        } catch (PDOException $e) {
                            if ($e->getCode() !== '23000') {
                                throw $e;
                            }
                            // $newEmail already belongs to a different
                            // requesters row -- point this ticket (and any
                            // siblings under the typo'd address) at it
                            // instead of renaming into a collision.
                            db()->prepare('UPDATE tickets SET requester_email = ? WHERE requester_email = ?')
                                ->execute([$newEmail, $oldEmail]);
                        }
                    }

                    // Covers both remaining cases: no requesters row existed
                    // yet (a ticket from before that table existed), and
                    // confirming name/phone on whichever row $newEmail now
                    // resolves to above.
                    db()->prepare(
                        'INSERT INTO requesters (email, name, phone) VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE name = VALUES(name), phone = VALUES(phone)'
                    )->execute([$newEmail, $newName, $newPhone]);

                    // requester_name is a denormalized copy on each ticket
                    // row, not tied to requesters by a foreign key -- fix it
                    // on every ticket from this requester too, not just this one.
                    db()->prepare('UPDATE tickets SET requester_name = ? WHERE requester_email = ?')
                        ->execute([$newName, $newEmail]);

                    db()->commit();
                    flash('success', 'Contact information updated.');
                } catch (Throwable $e) {
                    db()->rollBack();
                    flash('error', 'Could not update contact information.');
                }
            }

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

                // send_ticket_update_notification() needs the value the agent
                // just saved, not the one $ticket was fetched with at the top
                // of the request.
                $ticket['status'] = $status;
                $ticket['priority'] = $priority;

                $mailError = null;
                if (ticket_public_tokens_supported() && !empty($ticket['public_token'])) {
                    try {
                        send_ticket_update_notification($ticket, $response);
                    } catch (Throwable $e) {
                        $mailError = $e->getMessage();
                    }
                }

                if ($mailError !== null) {
                    flash('error', 'Ticket #' . $id . ' updated, but the notification email to the submitter failed to send: ' . $mailError);
                } else {
                    flash('success', 'Ticket #' . $id . ' updated.');
                }
                header('Location: ticket.php?id=' . $id);
                exit;
            }
        }
    }
}

$assignedGroupIds = ticket_assigned_group_ids($id);
$groups = assignable_groups();
$agents = active_agents();
$assignedAgent = ticket_assigned_agent($id);
$comments = ticket_comments($id);
$commentAttachments = ticket_comment_attachments_by_ticket($id);
$cannedResponses = all_canned_responses();

$assignedGroupNames = array_values(array_intersect_key(
    array_column($groups, 'name', 'id'),
    array_flip($assignedGroupIds)
));
$assigneeTooltipHtml = '<div class="text-start"><div class="fw-semibold border-bottom pb-1 mb-1">Assigned To</div>';
if ($assignedAgent) {
    $assigneeTooltipHtml .= '<div>Agent: ' . e($assignedAgent['full_name']) . '</div>';
}
$assigneeTooltipHtml .= $assignedGroupNames
    ? '<div>Groups: ' . e(implode(', ', $assignedGroupNames)) . '</div>'
    : ($assignedAgent ? '' : '<div>Unassigned</div>');
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
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Ticket #<?= (int) $ticket['id'] ?></span>
        <div class="d-flex align-items-center flex-wrap gap-2">
            <select id="quickPriority" class="form-select form-select-sm w-auto" name="priority" form="manageTicketForm" aria-label="Priority">
                <?php foreach (TICKET_PRIORITIES as $priority): ?>
                    <option value="<?= e($priority) ?>" <?= $ticket['priority'] === $priority ? 'selected' : '' ?>><?= e($priority) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="quickStatus" class="form-select form-select-sm w-auto" aria-label="Status">
                <?php foreach (TICKET_STATUSES as $status): ?>
                    <option value="<?= e($status) ?>" <?= $ticket['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
            <span id="quickUpdateStatus" class="small text-body-secondary" aria-live="polite" style="min-width: 3.5em;"></span>
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
                    <span class="visually-hidden">Assigned groups</span>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-4">
        <h1 class="h4"><?= e($ticket['subject']) ?></h1>
        <p class="text-body-secondary mb-4 d-flex align-items-center flex-wrap gap-2">
            <span>Submitted by <?= e($ticket['requester_name']) ?></span>
            <button type="button" class="btn btn-link btn-sm p-0 align-baseline text-decoration-none text-body-secondary" data-bs-toggle="modal" data-bs-target="#requesterInfoModal">(User Information)</button>
            <span>on <?= e(date('M j, Y g:i A', strtotime($ticket['created_at']))) ?></span>
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
                <?php
                $isInternal = (int) $comment['is_internal'] === 1;
                // A null author means the submitter posted it themselves --
                // via ticket-status.php, or via an inbound email reply (see
                // includes/imap.php) -- either way, never an internal note.
                $fromSubmitter = $comment['author_name'] === null;
                $authorName = $fromSubmitter ? $ticket['requester_name'] : $comment['author_name'];
                $attachments = $commentAttachments[$comment['id']] ?? [];
                ?>
                <div class="border-start <?= $isInternal ? 'border-warning' : 'border-primary' ?> border-3 <?= $isInternal ? 'bg-warning-subtle' : 'bg-body-tertiary' ?> rounded p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-start mb-1 gap-2">
                        <span class="badge <?= $isInternal ? 'text-bg-warning' : 'text-bg-primary' ?>"><?= $isInternal ? 'Internal Note' : ($fromSubmitter ? 'From Submitter' : 'Response to Submitter') ?></span>
                        <small class="text-body-secondary text-nowrap"><?= e(date('M j, Y g:i A', strtotime($comment['created_at']))) ?></small>
                    </div>
                    <p class="mb-1 fw-semibold"><?= e($authorName) ?></p>
                    <p class="mb-0" style="white-space: pre-wrap;"><?= e($comment['body']) ?></p>
                    <?php if ($attachments): ?>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <?php foreach ($attachments as $attachment): ?>
                                <a href="<?= e($attachment['path']) ?>" target="_blank" rel="noopener" class="badge text-bg-light text-decoration-none border">
                                    <?= e($attachment['original_filename'] ?: basename($attachment['path'])) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">Respond &amp; Manage Ticket</div>
    <div class="card-body p-4">
        <?php if (!ticket_assignments_supported()): ?>
            <div class="alert alert-warning small">Ticket assignment is unavailable until an administrator visits <a href="migrate.php">migrate.php</a> to update the database.</div>
        <?php endif; ?>
        <form method="post" id="manageTicketForm" data-loading-text="Saving...">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="manage_ticket">
            <?php if ($cannedResponses): ?>
                <div class="mb-3">
                    <label class="form-label" for="canned_response">Canned Response</label>
                    <select class="form-select form-select-sm" id="canned_response">
                        <option value="">Insert a canned response…</option>
                        <?php foreach ($cannedResponses as $cannedResponse): ?>
                            <option value="<?= e($cannedResponse['body']) ?>"><?= e($cannedResponse['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
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
                    <div class="mb-3">
                        <label class="form-label" for="assigned_agent_id">Assigned Agent</label>
                        <select class="form-select" id="assigned_agent_id" name="assigned_agent_id">
                            <option value="">Unassigned</option>
                            <?php foreach ($agents as $agent): ?>
                                <option value="<?= (int) $agent['id'] ?>" <?= $assignedAgent && (int) $assignedAgent['id'] === (int) $agent['id'] ? 'selected' : '' ?>><?= e($agent['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Once an agent is assigned, ticket emails go only to them instead of the whole group.</div>
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

<div class="modal fade" id="requesterInfoModal" tabindex="-1" aria-labelledby="requesterInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" id="requesterInfoForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_requester">
                <div class="modal-header">
                    <h5 class="modal-title" id="requesterInfoModalLabel">User Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-body-secondary small requester-edit-hint" hidden>Correct these if the submitter mistyped their own contact info — this updates every ticket from this requester.</p>
                    <div class="mb-3">
                        <label class="form-label" for="requester_name">Name</label>
                        <input class="form-control form-control-plaintext requester-field" id="requester_name" name="name" required maxlength="100" readonly value="<?= e($requester['name'] ?? $ticket['requester_name']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="requester_email">Email</label>
                        <input type="email" class="form-control form-control-plaintext requester-field" id="requester_email" name="email" required maxlength="150" readonly value="<?= e($requester['email'] ?? $ticket['requester_email']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="requester_phone">Phone</label>
                        <input class="form-control form-control-plaintext requester-field" id="requester_phone" name="phone" required maxlength="30" readonly value="<?= e($requester['phone'] ?? '') ?>">
                    </div>
                    <p class="text-body-secondary small mb-0">Tickets submitted: <?= $requesterTicketCount ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary requester-view-only" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary requester-view-only" id="requesterEditToggle">Edit</button>
                    <button type="button" class="btn btn-secondary requester-edit-only" id="requesterCancelEdit" hidden>Cancel</button>
                    <button type="submit" class="btn btn-primary requester-edit-only" hidden>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
