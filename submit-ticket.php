<?php
require __DIR__ . '/includes/bootstrap.php';

$errors = [];
$old = [
    'requester_name'  => '',
    'requester_email' => '',
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
    if ($old['subject'] === '') {
        $errors[] = 'Please enter a subject.';
    }
    if ($old['description'] === '') {
        $errors[] = 'Please describe the issue.';
    }
    if (!in_array($old['category'], TICKET_CATEGORIES, true)) {
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
        $stmt = db()->prepare(
            'INSERT INTO tickets (requester_name, requester_email, subject, description, category, priority, status, attachment_path)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $old['requester_name'],
            $old['requester_email'],
            $old['subject'],
            $old['description'],
            $old['category'],
            $old['priority'],
            'Open',
            $attachmentPath,
        ]);

        header('Location: ticket-submitted.php?id=' . db()->lastInsertId());
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
                    <div class="col-md-6">
                        <label class="form-label" for="category">Category</label>
                        <select class="form-select" id="category" name="category">
                            <?php foreach (TICKET_CATEGORIES as $category): ?>
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
