<?php

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/** Whether the users.theme column exists yet (migration 015). */
function user_theme_supported(): bool
{
    static $result = null;
    if ($result === null) {
        $result = column_exists('users', 'theme');
    }
    return $result;
}

/** The current agent's saved theme preference ('light'/'dark'), or null if they haven't set one -- follow the OS/browser default in that case. */
function current_user_theme(): ?string
{
    $user = current_user();
    return $user['theme'] ?? null;
}

/**
 * Saves the current agent's theme preference so it follows them to another
 * browser/device -- the localStorage the toggle also writes to only covers
 * this one browser, and is all an anonymous ticket submitter has, since
 * they have no account to persist it against.
 */
function set_current_user_theme(string $theme): void
{
    if (!in_array($theme, ['light', 'dark'], true) || !user_theme_supported()) {
        return;
    }
    $userId = current_user_id();
    if ($userId === null) {
        return;
    }
    db()->prepare('UPDATE users SET theme = ? WHERE id = ?')->execute([$theme, $userId]);
    $_SESSION['user']['theme'] = $theme;
}

function current_user_id(): ?int
{
    $user = current_user();
    return $user ? (int) $user['id'] : null;
}

/** Whether the users.must_reset_password column exists yet (migration 016). */
function must_reset_password_supported(): bool
{
    static $result = null;
    if ($result === null) {
        $result = column_exists('users', 'must_reset_password');
    }
    return $result;
}

/**
 * A random password for a newly created account -- alphanumeric only, and
 * excluding characters that look alike (0/O, 1/l/I) since a human may need
 * to read this out of an email rather than paste it. 16 characters from this
 * 58-character alphabet is far more entropy than an admin-chosen password
 * would realistically have, so no symbols are needed to make it strong.
 */
function generate_random_password(int $length = 16): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $max = strlen($alphabet) - 1;
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, $max)];
    }
    return $password;
}

function require_login(): void
{
    $user = current_user();
    if (!$user) {
        header('Location: login.php');
        exit;
    }

    try {
        $columns = 'disabled' . (must_reset_password_supported() ? ', must_reset_password' : '');
        $stmt = db()->prepare("SELECT {$columns} FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();
    } catch (PDOException $e) {
        // Schema is behind what this code expects (e.g. a pending migration
        // after a git pull). Fail closed rather than crash every page.
        logout();
        flash('error', 'The database is out of date. An administrator should visit migrate.php to update it.');
        header('Location: login.php');
        exit;
    }

    if (!$row || (int) $row['disabled'] === 1) {
        logout();
        header('Location: login.php');
        exit;
    }

    // Force a fresh account straight to the dedicated reset form -- everything
    // else is off limits until they've replaced the one an admin emailed them.
    $mustReset = !empty($row['must_reset_password']);
    if ($mustReset && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'reset-password.php') {
        flash('error', 'For your security, please set a new password before continuing.');
        header('Location: reset-password.php');
        exit;
    }
}

function is_admin(): bool
{
    static $result = null;
    if ($result === null) {
        $user = current_user();
        if (!$user) {
            $result = false;
        } else {
            try {
                $stmt = db()->prepare('SELECT is_admin FROM users WHERE id = ?');
                $stmt->execute([$user['id']]);
                $row = $stmt->fetch();
                $result = $row && (int) $row['is_admin'] === 1;
            } catch (PDOException $e) {
                // Schema is behind what this code expects — treat as non-admin
                // rather than crash the page (this runs on every page's nav).
                $result = false;
            }
        }
    }
    return $result;
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
    $columns = 'id, username, full_name, password_hash, disabled' . (user_theme_supported() ? ', theme' : '');
    $stmt = db()->prepare("SELECT {$columns} FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return 'invalid';
    }

    if ((int) $user['disabled'] === 1) {
        return 'disabled';
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'        => $user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'theme'     => $user['theme'] ?? null,
    ];

    return 'ok';
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
}
