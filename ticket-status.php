<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/mail.php';

$token = (string) ($_GET['token'] ?? '');
$ticket = null;

if ($token !== '' && ticket_public_tokens_supported()) {
    $stmt = db()->prepare('SELECT * FROM tickets WHERE public_token = ?');
    $stmt->execute([$token]);
    $ticket = $stmt->fetch() ?: null;
}

$error = null;

if ($ticket && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($body === '') {
            $error = 'Please enter a message.';
        } else {
            add_ticket_comment((int) $ticket['id'], null, $body, false);
            mark_ticket_submitter_activity((int) $ticket['id']);

            // A submitter replying on a Resolved/Closed ticket means it isn't
            // actually settled -- reopen it. This is what the "reply to
            // reopen" note in the update-notification email promises (see
            // includes/mail.php); until now nothing here actually did it.
            $reopened = in_array($ticket['status'], ['Resolved', 'Closed'], true);
            if ($reopened) {
                db()->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute(['Open', (int) $ticket['id']]);
                $ticket['status'] = 'Open';
            }

            send_ticket_reply_notification((int) $ticket['id'], $ticket['subject'], $ticket['status'], $body);

            flash('success', $reopened ? 'Your reply has been added and the ticket has been reopened.' : 'Your reply has been added.');
            header('Location: ticket-status.php?token=' . urlencode($token));
            exit;
        }
    }
}

$comments = $ticket ? ticket_public_comments((int) $ticket['id']) : [];
$commentAttachments = $ticket ? ticket_comment_attachments_by_ticket((int) $ticket['id']) : [];

$pageTitle = $ticket ? ('Ticket #' . $ticket['id']) : 'Ticket Status';
require __DIR__ . '/includes/header.php';
?>

<div class="form-shell">
    <?php if (!$ticket): ?>
        <div class="card">
            <div class="card-body p-4">
                <h1 class="h4">Ticket Not Found</h1>
                <p class="text-body-secondary mb-0">
                    We couldn't find a ticket for this link. It may be invalid, or the link may have come
                    from an older email if this helpdesk was recently reinstalled.
                </p>
            </div>
        </div>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <span>Ticket #<?= (int) $ticket['id'] ?></span>
                <span class="d-flex gap-2">
                    <span class="badge <?= status_badge_class($ticket['status']) ?>"><?= e($ticket['status']) ?></span>
                    <span class="badge <?= priority_badge_class($ticket['priority']) ?>"><?= e($ticket['priority']) ?></span>
                </span>
            </div>
            <div class="card-body p-4">
                <h1 class="h4"><?= e($ticket['subject']) ?></h1>
                <p class="text-body-secondary mb-4">
                    Submitted <?= e(date('M j, Y g:i A', strtotime($ticket['created_at']))) ?> &middot; <?= e($ticket['category']) ?>
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
                <?php if (!$comments): ?>
                    <p class="text-body-secondary mb-0">No responses yet.</p>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                        <?php $attachments = $commentAttachments[$comment['id']] ?? []; ?>
                        <div class="border-start border-primary border-3 bg-body-tertiary rounded p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start mb-1 gap-2">
                                <span class="fw-semibold"><?= e($comment['author_name'] ?? 'You') ?></span>
                                <small class="text-body-secondary text-nowrap"><?= e(date('M j, Y g:i A', strtotime($comment['created_at']))) ?></small>
                            </div>
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
            <div class="card-header">Add a Comment</div>
            <div class="card-body p-4">
                <form method="post" data-loading-text="Sending...">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="body">Your Message</label>
                        <textarea class="form-control" id="body" name="body" rows="4" required></textarea>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Send</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
