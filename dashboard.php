<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$statusFilter = $_GET['status'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$viewFilter = $_GET['view'] ?? 'mine';

$assignmentsSupported = ticket_assignments_supported();
$agentAssignmentSupported = ticket_assigned_agent_supported();
$isAdmin = is_admin();
// Administrators default to the same "assigned to me" view agents are
// stuck with, but can switch to everything via the My/All Tickets dropdown
// below.
$restrictToSelf = !$isAdmin || $viewFilter === 'mine';

$where = [];
$params = [];

if ($statusFilter === 'all') {
    // No status filter -- show every ticket regardless of status.
} elseif (in_array($statusFilter, TICKET_STATUSES, true)) {
    $where[] = 't.status = ?';
    $params[] = $statusFilter;
} else {
    // Default view: hide Resolved/Closed tickets so the queue only shows
    // what still needs attention.
    $where[] = 't.status IN (?, ?)';
    $params[] = 'Open';
    $params[] = 'In Progress';
}
if (in_array($categoryFilter, category_names(), true)) {
    $where[] = 't.category = ?';
    $params[] = $categoryFilter;
}

// Agents only see tickets assigned to one of their groups, or directly to
// them; administrators see everything unless they've switched to "My Tickets".
if ($restrictToSelf) {
    if ($assignmentsSupported) {
        $conditions = ['t.id IN (SELECT ticket_id FROM ticket_assigned_groups WHERE group_id IN (
                        SELECT group_id FROM user_agent_groups WHERE user_id = ?))'];
        $params[] = current_user_id();
        if ($agentAssignmentSupported) {
            $conditions[] = 't.assigned_agent_id = ?';
            $params[] = current_user_id();
        }
        $where[] = '(' . implode(' OR ', $conditions) . ')';
    } else {
        // Can't tell what's assigned to this agent yet — show nothing rather than everything.
        $where[] = '1 = 0';
    }
}

// The most recent of the ticket's own creation and any comment on it
// (agent response, submitter reply via the web link, or an inbound email
// reply) -- used below to sort by actual recent activity rather than just
// when the ticket was first opened.
$lastActivitySelect = ticket_comments_supported()
    ? 'COALESCE((SELECT MAX(created_at) FROM ticket_comments WHERE ticket_id = t.id), t.created_at) AS last_activity_at'
    : 't.created_at AS last_activity_at';

if ($assignmentsSupported) {
    $sql = 'SELECT t.*,
                GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ", ") AS assigned_group_names,
                ' . $lastActivitySelect
            . ($agentAssignmentSupported ? ', a.full_name AS assigned_agent_name' : ', NULL AS assigned_agent_name')
            . ' FROM tickets t
            LEFT JOIN ticket_assigned_groups tg ON tg.ticket_id = t.id
            LEFT JOIN agent_groups g ON g.id = tg.group_id'
            . ($agentAssignmentSupported ? ' LEFT JOIN users a ON a.id = t.assigned_agent_id' : '');
} else {
    // Migration 007 hasn't been run yet — the assignment table doesn't exist.
    $sql = 'SELECT t.*, NULL AS assigned_group_names, NULL AS assigned_agent_name, '
            . $lastActivitySelect . ' FROM tickets t';
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
if ($assignmentsSupported) {
    $sql .= ' GROUP BY t.id';
}
$sql .= ' ORDER BY last_activity_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$pageTitle = 'Ticket Queue';
require __DIR__ . '/includes/header.php';
?>

<?php if (!$assignmentsSupported): ?>
    <div class="alert alert-warning">The database is out of date — ticket assignment info is hidden until an administrator visits <a href="migrate.php">migrate.php</a>.</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
    <div>
        <h1 class="h3 mb-1">Ticket Queue</h1>
        <p class="text-body-secondary mb-0"><?= count($tickets) ?> ticket<?= count($tickets) === 1 ? '' : 's' ?></p>
    </div>
    <form class="d-flex gap-2" method="get">
        <?php if ($isAdmin): ?>
            <select class="form-select form-select-sm" name="view" onchange="this.form.submit()">
                <option value="mine" <?= $viewFilter === 'mine' ? 'selected' : '' ?>>My Tickets</option>
                <option value="all" <?= $viewFilter === 'all' ? 'selected' : '' ?>>All Tickets</option>
            </select>
        <?php endif; ?>
        <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
            <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>Open &amp; In Progress</option>
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
            <?php foreach (TICKET_STATUSES as $status): ?>
                <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e($status) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select form-select-sm" name="category" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <?php foreach (category_names() as $category): ?>
                <option value="<?= e($category) ?>" <?= $categoryFilter === $category ? 'selected' : '' ?>><?= e($category) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Subject</th>
                    <th>Requester</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>Last Activity</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$tickets): ?>
                    <tr><td colspan="8" class="text-center text-body-secondary py-4">No tickets found.</td></tr>
                <?php endif; ?>
                <?php foreach ($tickets as $ticket): ?>
                    <tr class="cursor-pointer" onclick="window.location='ticket.php?id=<?= (int) $ticket['id'] ?>'" style="cursor:pointer">
                        <td>
                            #<?= (int) $ticket['id'] ?>
                            <?php if (ticket_is_new($ticket)): ?>
                                <span class="badge text-bg-primary">New</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($ticket['subject']) ?></td>
                        <td><?= e($ticket['requester_name']) ?></td>
                        <td><?= e($ticket['category']) ?></td>
                        <td><span class="badge <?= priority_badge_class($ticket['priority']) ?>"><?= e($ticket['priority']) ?></span></td>
                        <td><span class="badge <?= status_badge_class($ticket['status']) ?>"><?= e($ticket['status']) ?></span></td>
                        <td><?= e($ticket['assigned_agent_name'] ?: ($ticket['assigned_group_names'] ?: '—')) ?></td>
                        <td><?= e(date('M j, Y g:i A', strtotime($ticket['last_activity_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
