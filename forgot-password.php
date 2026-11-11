<?php
// "Forgot password?" on the login page: type a username or email address and
// a one-time link to choose a new password is emailed. See
// includes/password-resets.php.
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/mail.php';
require_once __DIR__ . '/includes/password-resets.php';

if (current_user()) {
    header('Location: account.php');
    exit;
}

if (!password_resets_available()) {
    flash('error', 'Resetting a password by email is not set up. Ask an administrator.');
    header('Location: login.php');
    exit;
}

$error = null;
$typed = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $typed = trim((string) ($_POST['identifier'] ?? ''));

    if (!verify_csrf()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        // Whoever asks gets the same answer, whether or not an account matched,
        // and gets it at the same speed: the email is sent after the response
        // has gone, so how long it takes can't show whether there was anyone
        // to write to.
        $user = find_user_for_password_reset($typed);
        $secret = null;
        if ($user !== null && !password_reset_throttled((int) $user['id'], $ip)) {
            $secret = issue_password_reset((int) $user['id'], $ip);
        }

        flash('success', 'If that matches an account with an email address, a link to choose a new password is on its way. It works once and expires in ' . PASSWORD_RESET_LIFETIME_MINUTES . ' minutes.');

        ignore_user_abort(true);
        http_response_code(303);
        header('Location: login.php');
        header('Content-Length: 0');
        header('Connection: close');
        session_write_close(); // the flash is saved before we let go of the browser
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }

        if ($secret !== null && $user !== null) {
            try {
                send_password_reset_email(
                    (string) $user['email'],
                    (string) $user['full_name'],
                    (string) $user['username'],
                    base_url() . '/choose-password.php?token=' . $secret,
                    PASSWORD_RESET_LIFETIME_MINUTES
                );
            } catch (Throwable $e) {
                error_log('Could not send a password reset email for user #' . $user['id'] . ': ' . $e->getMessage());
            }
        }
        exit;
    }
}

$pageTitle = 'Forgot Password';
require __DIR__ . '/includes/header.php';
?>

<div class="auth-shell">
    <div class="mb-4 text-center">
        <h1 class="h3">Forgot your password?</h1>
        <p class="text-body-secondary">Enter your username or email address and we'll send you a link to choose a new one.</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-4">
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="identifier">Username or email</label>
                    <input class="form-control" id="identifier" name="identifier" required autofocus maxlength="150"
                           autocapitalize="off" spellcheck="false" value="<?= e($typed) ?>">
                </div>
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">Send the link</button>
                </div>
            </form>
        </div>
    </div>
    <p class="text-center mt-3"><a href="login.php" class="text-decoration-none">&larr; Back to login</a></p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
