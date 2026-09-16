<?php
// Persists the dark-mode toggle (assets/js/theme-toggle.js) for a logged-in
// agent, so it follows them to another browser/device. Anonymous visitors
// have no account to persist it against -- the toggle's own localStorage
// write already covers them for this browser, and this endpoint just does
// nothing for them rather than erroring.
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

if (current_user_id() !== null) {
    set_current_user_theme((string) ($_POST['theme'] ?? ''));
}

echo json_encode(['ok' => true]);
