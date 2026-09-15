<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/mail.php';

$errors = [];
$old = [
    'requester_name'  => '',
    'requester_email' => '',
    'requester_phone' => '',
    'subject'         => '',
    'description'     => '',
    'category'        => 'General',
    'priority'        => 'Medium',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Your session expired. Please try again.';
    }

    foreach (array_keys($old) as $field) {
        $old[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    if ($old['requester_name'] === '') {
        $errors[] = 'Please enter your name.';
    }
    if (!filter_var($old['requester_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($old['requester_phone']) > 30) {
        $errors[] = 'Phone number is too long (30 characters max).';
    }
    if ($old['subject'] === '') {
        $errors[] = 'Please enter a subject.';
    }
    if ($old['description'] === '') {
        $errors[] = 'Please describe the issue.';
    }
    if (!in_array($old['category'], category_names(), true)) {
        $errors[] = 'Please choose a valid category.';
    }
    if (!in_array($old['priority'], TICKET_PRIORITIES, true)) {
        $errors[] = 'Please choose a valid priority.';
    }

    $attachmentPath = null;
    if (!$errors) {
        try {
            $attachmentPath = store_ticket_attachment($_FILES['screenshot'] ?? []);
        } catch (InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!$errors) {
        $phone = $old['requester_phone'] !== '' ? $old['requester_phone'] : null;

        db()->beginTransaction();

        $stmt = db()->prepare(
            'INSERT INTO requesters (email, name, phone) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), phone = COALESCE(VALUES(phone), phone)'
        );
        $stmt->execute([$old['requester_email'], $old['requester_name'], $phone]);

        $ticketValues = [
            $old['requester_name'],
            $old['requester_email'],
            $old['subject'],
            $old['description'],
            $old['category'],
            $old['priority'],
            'Open',
            $attachmentPath,
        ];
        $ticketSql = 'INSERT INTO tickets (requester_name, requester_email, subject, description, category, priority, status, attachment_path';

        // Column may not exist yet on a database that's pending migrate.php --
        // degrade gracefully rather than fail the whole ticket submission.
        $publicToken = null;
        if (ticket_public_tokens_supported()) {
            $publicToken = bin2hex(random_bytes(32));
            $ticketSql .= ', public_token';
            $ticketValues[] = $publicToken;
        }

        $stmt = db()->prepare($ticketSql . ') VALUES (' . implode(', ', array_fill(0, count($ticketValues), '?')) . ')');
        $stmt->execute($ticketValues);
        $newTicketId = (int) db()->lastInsertId();

        assign_ticket_by_category($newTicketId, $old['category']);

        db()->commit();

        if ($publicToken !== null) {
            try {
                send_ticket_confirmation_email([
                    'id'              => $newTicketId,
                    'subject'         => $old['subject'],
                    'requester_name'  => $old['requester_name'],
                    'requester_email' => $old['requester_email'],
                    'public_token'    => $publicToken,
                ]);
            } catch (Throwable $e) {
                // Don't let a mail failure block ticket creation -- the ticket
                // itself is already committed. Just leave a trail for an admin.
                error_log('Failed to send ticket confirmation email for ticket #' . $newTicketId . ': ' . $e->getMessage());
            }
        }

        send_new_ticket_notification($newTicketId, $old['subject']);

        header('Location: ticket-submitted.php?id=' . $newTicketId);
        exit;
    }
}

$pageTitle = 'Submit a Ticket';
require __DIR__ . '/includes/header.php';
?>

<div class="form-shell">
    <div class="mb-4">
        <h1 class="h3">Submit a Ticket</h1>
        <p class="text-body-secondary">Let us know what's going on. No account required.</p>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-4">
            <form method="post" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="requester_name">Your Name</label>
                        <input class="form-control" id="requester_name" name="requester_name" required value="<?= e($old['requester_name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="requester_email">Email Address</label>
                        <input type="email" class="form-control" id="requester_email" name="requester_email" required value="<?= e($old['requester_email']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="requester_phone">Phone <span class="text-body-secondary">(optional)</span></label>
                        <input class="form-control" id="requester_phone" name="requester_phone" maxlength="30" value="<?= e($old['requester_phone']) ?>">
                    </div>
                    <div class="w-100"></div>
                    <div class="col-md-6">
                        <label class="form-label" for="category">Category</label>
                        <select class="form-select" id="category" name="category">
                            <?php foreach (category_names() as $category): ?>
                                <option value="<?= e($category) ?>" <?= $old['category'] === $category ? 'selected' : '' ?>><?= e($category) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="priority">Priority</label>
                        <select class="form-select" id="priority" name="priority">
                            <?php foreach (TICKET_PRIORITIES as $priority): ?>
                                <option value="<?= e($priority) ?>" <?= $old['priority'] === $priority ? 'selected' : '' ?>><?= e($priority) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="subject">Subject</label>
                        <input class="form-control" id="subject" name="subject" required value="<?= e($old['subject']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">Describe the Issue</label>
                        <textarea class="form-control" id="description" name="description" rows="5" required><?= e($old['description']) ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="screenshot">Screenshot (optional)</label>
                        <input type="file" class="form-control" id="screenshot" name="screenshot" accept="image/png,image/jpeg,image/gif,image/webp">
                        <div class="form-text">PNG, JPEG, GIF, or WEBP. 5 MB max.</div>
                    </div>
                </div>
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">Submit Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
