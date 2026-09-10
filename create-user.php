<?php
// CLI helper for creating helpdesk staff accounts.
// Usage: php create-user.php <username> <full name>
// You will be prompted for a password.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require __DIR__ . '/includes/db.php';

$username = $argv[1] ?? null;
$fullName = $argv[2] ?? null;

if (!$username || !$fullName) {
    fwrite(STDERR, "Usage: php create-user.php <username> <full name>\n");
    exit(1);
}

fwrite(STDOUT, "Password: ");
$password = trim((string) fgets(STDIN));

if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}

$stmt = db()->prepare('INSERT INTO users (username, password_hash, full_name) VALUES (?, ?, ?)');
$stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $fullName]);

fwrite(STDOUT, "Created helpdesk user '{$username}'.\n");
