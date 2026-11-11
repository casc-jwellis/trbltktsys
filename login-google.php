<?php
// Receives the signed token Google's button hands the browser (posted here by
// assets/js/google-signin.js as an ordinary form, with our CSRF token). It's
// believed only after GoogleIdToken has checked the signature and that it was
// made for this site and this sign-in page; the account is then the one active
// user whose email address is the verified one in the token. See
// includes/google-signin.php.
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/google-signin.php';

if (current_user() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . (current_user() ? 'dashboard.php' : 'login.php'));
    exit;
}

/** Back to the login page with a message. */
function google_signin_fail(string $message): never
{
    flash('error', $message);
    header('Location: login.php');
    exit;
}

if (!verify_csrf()) {
    google_signin_fail('Your session expired. Please try again.');
}

$clientId = google_signin_client_id();
$nonce = $_SESSION['google_nonce'] ?? null;
unset($_SESSION['google_nonce']); // one try per page load, whatever happens

if ($clientId === null || !is_string($nonce)) {
    google_signin_fail('Sign in with Google is not available right now. Reload the page and try again.');
}

try {
    $claims = (new GoogleIdToken(GoogleIdToken::cacheFile()))->verify((string) ($_POST['credential'] ?? ''), $clientId, $nonce);
} catch (RuntimeException $e) {
    error_log('Google sign-in refused: ' . $e->getMessage());
    google_signin_fail('Google sign-in could not be verified. Please try again.');
}

$domain = (string) get_google_signin_settings()['allowed_domain'];
if (!google_claims_on_domain($claims, $domain)) {
    google_signin_fail('Only @' . $domain . ' Google accounts can sign in this way.');
}

$email = (string) $claims['email'];
try {
    $columns = 'id, username, full_name, disabled' . (user_theme_supported() ? ', theme' : '')
        . (must_reset_password_supported() ? ', must_reset_password' : '');
    $stmt = db()->prepare("SELECT {$columns} FROM users WHERE LOWER(email) = LOWER(?)");
    $stmt->execute([$email]);
    $matches = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Google sign-in lookup failed: ' . $e->getMessage());
    google_signin_fail('The database is out of date. An administrator should run the pending database updates (Admin Settings, Database tab).');
}

if (count($matches) === 0) {
    google_signin_fail('No helpdesk account uses ' . $email . '. Ask an administrator to add that address to your account.');
}
if (count($matches) > 1) {
    google_signin_fail('More than one helpdesk account uses ' . $email . ', so it is not clear which is yours. Ask an administrator to fix that.');
}

$user = $matches[0];
if ((int) $user['disabled'] === 1) {
    google_signin_fail('This account has been disabled. Contact an administrator.');
}

// Someone still on the temporary password an admin emailed them has just proved
// who they are another way, so that password isn't needed: make it unusable
// and skip the forced reset. (If they ever need to sign in without Google, they
// can choose a password with "Forgot password?" on the login page.)
if (!empty($user['must_reset_password'])) {
    db()->prepare('UPDATE users SET password_hash = ?, must_reset_password = 0 WHERE id = ?')
        ->execute([password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $user['id']]);
}

start_user_session($user);

header('Location: dashboard.php');
exit;
