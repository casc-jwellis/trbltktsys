<?php

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function current_user_id(): ?int
{
    $user = current_user();
    return $user ? (int) $user['id'] : null;
}

function require_login(): void
{
    $user = current_user();
    if (!$user) {
        header('Location: login.php');
        exit;
    }

    $stmt = db()->prepare('SELECT is_locked FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();

    if (!$row || (int) $row['is_locked'] === 1) {
        logout();
        header('Location: login.php');
        exit;
    }
}

function user_role_names(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT r.name FROM roles r
         INNER JOIN user_roles ur ON ur.role_id = r.id
         WHERE ur.user_id = ?'
    );
    $stmt->execute([$userId]);
    return array_column($stmt->fetchAll(), 'name');
}

function current_user_roles(): array
{
    static $roles = null;
    if ($roles === null) {
        $user = current_user();
        $roles = $user ? user_role_names((int) $user['id']) : [];
    }
    return $roles;
}

function is_admin(): bool
{
    return in_array('Administrator', current_user_roles(), true);
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        flash('error', 'You do not have permission to access that page.');
        header('Location: dashboard.php');
        exit;
    }
}

function attempt_login(string $username, string $password): string
{
    $stmt = db()->prepare('SELECT id, username, full_name, password_hash, is_locked FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return 'invalid';
    }

    if ((int) $user['is_locked'] === 1) {
        return 'locked';
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'        => $user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
    ];

    return 'ok';
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
}
