<?php
require __DIR__ . '/includes/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$columns = 'id, subject, created_at' . (ticket_public_tokens_supported() ? ', public_token' : '');
$stmt = db()->prepare("SELECT {$columns} FROM tickets WHERE id = ?");
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
            <?php if (!empty($ticket['public_token'])): ?>
                <p class="mb-4">
                    <a href="<?= e('ticket-status.php?token=' . $ticket['public_token']) ?>">Check status &amp; add comments</a>
                </p>
                <p class="text-body-secondary small mb-4">We also emailed you this link — bookmark it to check back anytime.</p>
            <?php endif; ?>
            <a href="submit-ticket.php" class="btn btn-outline-primary">Submit Another Ticket</a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
