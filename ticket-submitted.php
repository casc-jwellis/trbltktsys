<?php
require __DIR__ . '/includes/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT id, subject, created_at FROM tickets WHERE id = ?');
$stmt->execute([$id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: submit-ticket.php');
    exit;
}

$pageTitle = 'Ticket Submitted';
require __DIR__ . '/includes/header.php';
?>

<div class="form-shell text-center">
    <div class="card">
        <div class="card-body p-5">
            <h1 class="h3 mb-3">Thanks — your ticket is in!</h1>
            <p class="text-body-secondary">Our helpdesk team will reach out by email as they work on it.</p>
            <p class="fs-4 fw-semibold my-4">Ticket #<?= e((string) $ticket['id']) ?></p>
            <p class="text-body-secondary mb-4"><?= e($ticket['subject']) ?></p>
            <a href="submit-ticket.php" class="btn btn-outline-primary">Submit Another Ticket</a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
