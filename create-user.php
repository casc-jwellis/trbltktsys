<?php
// CLI helper for creating helpdesk staff accounts.
// Usage: php create-user.php <username> <full name> [--admin] [--agent]
// You will be prompted for a password.
// If neither --admin nor --agent is given, the account is created as a Helpdesk Agent.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require __DIR__ . '/includes/db.php';

$username = $argv[1] ?? null;
$fullName = $argv[2] ?? null;

if (!$username || !$fullName) {
    fwrite(STDERR, "Usage: php create-user.php <username> <full name> [--admin] [--agent]\n");
    exit(1);
}

$flags = array_slice($argv, 3);
$wantsAdmin = in_array('--admin', $flags, true);
$wantsAgent = in_array('--agent', $flags, true);
if (!$wantsAdmin && !$wantsAgent) {
    $wantsAgent = true;
}

fwrite(STDOUT, "Password: ");
$password = trim((string) fgets(STDIN));

if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}

$roleNames = array_filter([
    $wantsAdmin ? 'Administrator' : null,
    $wantsAgent ? 'Helpdesk Agent' : null,
]);

$pdo = db();
$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, full_name) VALUES (?, ?, ?)');
    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $fullName]);
    $userId = (int) $pdo->lastInsertId();

    $roleStmt = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE name = ?');
    foreach ($roleNames as $roleName) {
        $roleStmt->execute([$userId, $roleName]);
    }

    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Could not create user: {$e->getMessage()}\n");
    exit(1);
}

fwrite(STDOUT, "Created helpdesk user '{$username}' with role(s): " . implode(', ', $roleNames) . "\n");
