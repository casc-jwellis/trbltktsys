<?php
// The one-time links behind "Forgot password" (table password_resets, migration
// 025). Ported from the web app template's modules/Auth/PasswordResets.php.
//
// The link carries a random secret; only its SHA-256 hash is stored, so a copy
// of the database can't be used to reset anyone's password. A link works once,
// for an hour, and a new request replaces the old one. Nothing here ever tells
// the person asking whether an account exists. Times are UTC strings built in
// PHP (not database defaults), so they compare the same however the database
// server's time zone is set.

const PASSWORD_RESET_LIFETIME_MINUTES = 60;
/** One link per account at most this often. */
const PASSWORD_RESET_COOLDOWN_SECONDS = 60;
/** Requests that produced a link, per address and hour. */
const PASSWORD_RESET_PER_IP_HOUR = 10;

/** Whether the password_resets table exists yet (migration 025). */
function password_resets_supported(): bool
{
    static $result = null;
    if ($result === null) {
        $result = table_exists('password_resets');
    }
    return $result;
}

/** Is the feature usable: the table is there and there's a mail server to send the link through. */
function password_resets_available(): bool
{
    if (!password_resets_supported() || !smtp_settings_supported()) {
        return false;
    }
    $smtp = get_smtp_settings();
    return $smtp && $smtp['host'] !== '' && !empty($smtp['from_email']);
}

/**
 * The account a person means by what they typed: a username, else an email
 * address that belongs to exactly one account. Null if there is none, it's
 * disabled, it has no valid email address, or the address is shared (we
 * wouldn't know who to write to).
 */
function find_user_for_password_reset(string $typed): ?array
{
    $typed = trim($typed);
    if ($typed === '' || mb_strlen($typed) > 150 || preg_match('/[\x00-\x1f\x7f]/', $typed)) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, username, full_name, email FROM users WHERE username = ? AND disabled = 0');
    $stmt->execute([$typed]);
    $user = $stmt->fetch() ?: null;

    if ($user === null && str_contains($typed, '@')) {
        $stmt = db()->prepare('SELECT id, username, full_name, email FROM users WHERE LOWER(email) = LOWER(?) AND disabled = 0 LIMIT 2');
        $stmt->execute([$typed]);
        $rows = $stmt->fetchAll();
        $user = count($rows) === 1 ? $rows[0] : null;
    }

    if ($user === null || !filter_var((string) $user['email'], FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    return $user;
}

/** Too many links asked for from this address lately, or one for this account a moment ago? */
function password_reset_throttled(int $userId, string $ip): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM password_resets WHERE requested_ip = ? AND created_at > ?');
    $stmt->execute([$ip, gmdate('Y-m-d H:i:s', time() - 3600)]);
    if ((int) $stmt->fetchColumn() >= PASSWORD_RESET_PER_IP_HOUR) {
        return true;
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > ?');
    $stmt->execute([$userId, gmdate('Y-m-d H:i:s', time() - PASSWORD_RESET_COOLDOWN_SECONDS)]);
    return (int) $stmt->fetchColumn() > 0;
}

/** A fresh link secret for this account, replacing any earlier one. Returns the secret (it isn't stored). */
function issue_password_reset(int $userId, string $ip): string
{
    db()->prepare('DELETE FROM password_resets WHERE expires_at < ?')->execute([gmdate('Y-m-d H:i:s')]);

    $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $now = time();
    db()->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$userId]);
    db()->prepare('INSERT INTO password_resets (user_id, token_hash, created_at, expires_at, requested_ip) VALUES (?, ?, ?, ?, ?)')
        ->execute([
            $userId,
            hash('sha256', $secret),
            gmdate('Y-m-d H:i:s', $now),
            gmdate('Y-m-d H:i:s', $now + PASSWORD_RESET_LIFETIME_MINUTES * 60),
            substr($ip, 0, 45),
        ]);
    return $secret;
}

/** The account a link belongs to, if the link is genuine, unused, unexpired and the account is still active. */
function lookup_password_reset(string $secret): ?array
{
    if (preg_match('/^[A-Za-z0-9_-]{43}$/', $secret) !== 1) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT u.id, u.username
           FROM password_resets r JOIN users u ON u.id = r.user_id
          WHERE r.token_hash = ? AND r.expires_at > ? AND u.disabled = 0'
    );
    $stmt->execute([hash('sha256', $secret), gmdate('Y-m-d H:i:s')]);
    return $stmt->fetch() ?: null;
}

/** Every link for this account is spent (after a successful reset). */
function clear_password_resets(int $userId): void
{
    db()->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$userId]);
}
