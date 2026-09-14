<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$statusFilter = $_GET['status'] ?? '';
$categoryFilter = $_GET['category'] ?? '';

$assignmentsSupported = ticket_assignments_supported();
$isAdmin = is_admin();

$where = [];
$params = [];

if (in_array($statusFilter, TICKET_STATUSES, true)) {
    $where[] = 't.status = ?';
    $params[] = $statusFilter;
}
if (in_array($categoryFilter, category_names(), true)) {
    $where[] = 't.category = ?';
    $params[] = $categoryFilter;
}

// Agents only see tickets assigned to them (directly or via one of their
// groups); administrators see everything.
if (!$isAdmin) {
    if ($assignmentsSupported) {
        $where[] = '(t.id IN (SELECT ticket_id FROM ticket_assigned_users WHERE user_id = ?)
                     OR t.id IN (SELECT ticket_id FROM ticket_assigned_groups WHERE group_id IN (
                         SELECT group_id FROM user_agent_groups WHERE user_id = ?)))';
        $params[] = current_user_id();
        $params[] = current_user_id();
    } else {
        // Can't tell what's assigned to this agent yet — show nothing rather than everything.
        $where[] = '1 = 0';
    }
}

if ($assignmentsSupported) {
    $sql = 'SELECT t.*,
                GROUP_CONCAT(DISTINCT u.full_name ORDER BY u.full_name SEPARATOR ", ") AS assigned_user_names,
                GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ", ") AS assigned_group_names
            FROM tickets t
            LEFT JOIN ticket_assigned_users tu ON tu.ticket_id = t.id
            LEFT JOIN users u ON u.id = tu.user_id
            LEFT JOIN ticket_assigned_groups tg ON tg.ticket_id = t.id
            LEFT JOIN agent_groups g ON g.id = tg.group_id';
} else {
    // Migration 007 hasn't been run yet — the assignment tables don't exist.
    $sql = 'SELECT t.*, NULL AS assigned_user_names, NULL AS assigned_group_names FROM tickets t';
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
if ($assignmentsSupported) {
    $sql .= ' GROUP BY t.id';
}
$sql .= ' ORDER BY
            CASE t.status WHEN "Open" THEN 0 WHEN "In Progress" THEN 1 WHEN "Resolved" THEN 2 ELSE 3 END,
            CASE t.priority WHEN "Urgent" THEN 0 WHEN "High" THEN 1 WHEN "Medium" THEN 2 ELSE 3 END,
            t.created_at DESC';

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
        <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
            <option value="">All Statuses</option>
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
                    <th>Submitted</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$tickets): ?>
                    <tr><td colspan="8" class="text-center text-body-secondary py-4">No tickets found.</td></tr>
                <?php endif; ?>
                <?php foreach ($tickets as $ticket): ?>
                    <tr class="cursor-pointer" onclick="window.location='ticket.php?id=<?= (int) $ticket['id'] ?>'" style="cursor:pointer">
                        <td>#<?= (int) $ticket['id'] ?></td>
                        <td><?= e($ticket['subject']) ?></td>
                        <td><?= e($ticket['requester_name']) ?></td>
                        <td><?= e($ticket['category']) ?></td>
                        <td><span class="badge <?= priority_badge_class($ticket['priority']) ?>"><?= e($ticket['priority']) ?></span></td>
                        <td><span class="badge <?= status_badge_class($ticket['status']) ?>"><?= e($ticket['status']) ?></span></td>
                        <td><?= e(implode(', ', array_filter([$ticket['assigned_user_names'], $ticket['assigned_group_names']])) ?: '—') ?></td>
                        <td><?= e(date('M j, Y g:i A', strtotime($ticket['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
