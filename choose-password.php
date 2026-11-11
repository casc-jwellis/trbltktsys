<?php
// The page a "Forgot password" email links to: ?token=<one-time secret>. Not to
// be confused with reset-password.php, which is the forced change for a signed-in
// account still on an emailed temporary password. See includes/password-resets.php.
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/password-resets.php';

// The secret is in this page's address: keep it out of the Referer header sent to
// the CDNs that serve the page's stylesheet and font.
header('Referrer-Policy: no-referrer');

if (current_user()) {
    header('Location: account.php');
    exit;
}

$secret = (string) ($_GET['token'] ?? '');
$resetUser = password_resets_supported() ? lookup_password_reset($secret) : null;
$error = null;

if ($resetUser !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $newPasswordConfirm = (string) ($_POST['new_password_confirm'] ?? '');

    if (!verify_csrf()) {
        $error = 'Your session expired. Please try again.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($newPassword !== $newPasswordConfirm) {
        $error = 'Passwords do not match.';
    } else {
        // Done: this link and any other are spent, and a temporary password no
        // longer needs replacing.
        $resetClause = must_reset_password_supported() ? ', must_reset_password = 0' : '';
        db()->prepare("UPDATE users SET password_hash = ?{$resetClause} WHERE id = ?")
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $resetUser['id']]);
        clear_password_resets((int) $resetUser['id']);

        flash('success', 'Your password has been changed. Log in with the new one.');
        header('Location: login.php');
        exit;
    }
}

$pageTitle = 'Choose a New Password';
require __DIR__ . '/includes/header.php';
?>

<div class="auth-shell">
    <?php if ($resetUser === null): ?>
        <div class="card">
            <div class="card-body p-4">
                <h1 class="h4 mb-2">Link not valid</h1>
                <p class="text-body-secondary">This link is wrong, has already been used, has expired, or the account can no longer be reset.</p>
                <?php if (password_resets_supported()): ?>
                    <a href="forgot-password.php" class="btn btn-primary">Get a new link</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="mb-4 text-center">
            <h1 class="h3">Choose a new password</h1>
            <p class="text-body-secondary">For <?= e($resetUser['username']) ?></p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body p-4">
                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="new_password">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8" autofocus autocomplete="new-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="new_password_confirm">Confirm New Password</label>
                        <input type="password" class="form-control" id="new_password_confirm" name="new_password_confirm" required minlength="8" autocomplete="new-password">
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
