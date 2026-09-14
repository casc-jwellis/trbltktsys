<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT * FROM tickets WHERE id = ?');
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
        $assignedTo = $_POST['assigned_to'] !== '' ? (int) $_POST['assigned_to'] : null;
        $internalNotes = trim((string) ($_POST['internal_notes'] ?? ''));

        if (!in_array($status, TICKET_STATUSES, true) || !in_array($priority, TICKET_PRIORITIES, true)) {
            $error = 'Please choose valid values.';
        } else {
            $stmt = db()->prepare(
                'UPDATE tickets SET status = ?, priority = ?, assigned_to = ?, internal_notes = ? WHERE id = ?'
            );
            $stmt->execute([$status, $priority, $assignedTo, $internalNotes, $id]);

            flash('success', 'Ticket #' . $id . ' updated.');
            header('Location: ticket.php?id=' . $id);
            exit;
        }
    }
}

$stmt = db()->prepare(
    'SELECT id, full_name FROM users WHERE is_locked = 0 OR id = ? ORDER BY full_name'
);
$stmt->execute([$ticket['assigned_to']]);
$agents = $stmt->fetchAll();

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
                    (<a href="mailto:<?= e($ticket['requester_email']) ?>"><?= e($ticket['requester_email']) ?></a>)
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
                    <div class="mb-3">
                        <label class="form-label" for="assigned_to">Assigned To</label>
                        <select class="form-select" id="assigned_to" name="assigned_to">
                            <option value="">Unassigned</option>
                            <?php foreach ($agents as $agent): ?>
                                <option value="<?= (int) $agent['id'] ?>" <?= (int) $ticket['assigned_to'] === (int) $agent['id'] ? 'selected' : '' ?>><?= e($agent['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
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
