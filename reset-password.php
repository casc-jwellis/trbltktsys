<?php
// The only page a "must reset password" account can reach -- see the check
// in require_login(). Deliberately minimal: just the two password fields,
// no profile fields, since that's all this step needs.
require __DIR__ . '/includes/bootstrap.php';
require_login();

$userId = current_user_id();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $newPasswordConfirm = (string) ($_POST['new_password_confirm'] ?? '');

        if (strlen($newPassword) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($newPassword !== $newPasswordConfirm) {
            $error = 'Passwords do not match.';
        } else {
            $resetClause = must_reset_password_supported() ? ', must_reset_password = 0' : '';
            db()->prepare("UPDATE users SET password_hash = ?{$resetClause} WHERE id = ?")
                ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);

            flash('success', 'Your password has been updated.');
            header('Location: dashboard.php');
            exit;
        }
    }
}

$pageTitle = 'Set a New Password';
require __DIR__ . '/includes/header.php';
?>

<div class="auth-shell">
    <div class="card">
        <div class="card-body p-4">
            <h1 class="h4 mb-2">Set a New Password</h1>
            <p class="text-body-secondary mb-4">For your security, please choose a new password before continuing.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="new_password">New Password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8" autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="new_password_confirm">Confirm New Password</label>
                    <input type="password" class="form-control" id="new_password_confirm" name="new_password_confirm" required minlength="8">
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
