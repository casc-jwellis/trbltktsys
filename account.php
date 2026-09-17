<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$userId = current_user_id();
$stmt = db()->prepare('SELECT username, full_name, email, phone, password_hash FROM users WHERE id = ?');
$stmt->execute([$userId]);
$account = $stmt->fetch();

$old = [
    'full_name' => $account['full_name'],
    'username'  => $account['username'],
    'email'     => (string) $account['email'],
    'phone'     => (string) $account['phone'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    if (!verify_csrf()) {
        $errors[] = 'Your session expired. Please try again.';
    }

    $old['full_name'] = trim((string) ($_POST['full_name'] ?? ''));
    $old['username']  = trim((string) ($_POST['username'] ?? ''));
    $old['email']     = trim((string) ($_POST['email'] ?? ''));
    $old['phone']     = trim((string) ($_POST['phone'] ?? ''));
    $currentPassword    = (string) ($_POST['current_password'] ?? '');
    $newPassword        = (string) ($_POST['new_password'] ?? '');
    $newPasswordConfirm = (string) ($_POST['new_password_confirm'] ?? '');

    if ($old['full_name'] === '') {
        $errors[] = 'Please enter your full name.';
    }
    if ($old['username'] === '' || strlen($old['username']) > 50) {
        $errors[] = 'Please enter a username (up to 50 characters).';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($old['phone']) > 30) {
        $errors[] = 'Phone number is too long (30 characters max).';
    }
    if ($newPassword !== '' && strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }
    if ($newPassword !== $newPasswordConfirm) {
        $errors[] = 'New passwords do not match.';
    }

    $sensitiveChange = $old['username'] !== $account['username']
        || $old['email'] !== (string) $account['email']
        || $newPassword !== '';

    if (!$errors && $sensitiveChange && !password_verify($currentPassword, $account['password_hash'])) {
        $errors[] = 'Your current password is required to change your username, email, or password.';
    }

    if (!$errors) {
        try {
            $phone = $old['phone'] !== '' ? $old['phone'] : null;

            if ($newPassword !== '') {
                $resetClause = must_reset_password_supported() ? ', must_reset_password = 0' : '';
                $stmt = db()->prepare(
                    "UPDATE users SET username = ?, full_name = ?, email = ?, phone = ?, password_hash = ?{$resetClause} WHERE id = ?"
                );
                $stmt->execute([$old['username'], $old['full_name'], $old['email'], $phone, password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
            } else {
                $stmt = db()->prepare('UPDATE users SET username = ?, full_name = ?, email = ?, phone = ? WHERE id = ?');
                $stmt->execute([$old['username'], $old['full_name'], $old['email'], $phone, $userId]);
            }

            $_SESSION['user']['username'] = $old['username'];
            $_SESSION['user']['full_name'] = $old['full_name'];

            flash('success', 'Your account has been updated.');
            header('Location: account.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = $e->getCode() === '23000' ? 'That username is already in use.' : 'Could not update your account.';
        }
    }

    if ($errors) {
        flash('error', implode(' ', $errors));
    }
}

$pageTitle = 'My Account';
require __DIR__ . '/includes/header.php';
?>

<div class="form-shell">
    <div class="mb-4">
        <h1 class="h3">My Account</h1>
        <p class="text-body-secondary">Update your profile and login details.</p>
    </div>

    <div class="card">
        <div class="card-body p-4">
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="full_name">Full Name</label>
                        <input class="form-control" id="full_name" name="full_name" required maxlength="100" value="<?= e($old['full_name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="phone">Phone <span class="text-body-secondary">(optional)</span></label>
                        <input class="form-control" id="phone" name="phone" maxlength="30" value="<?= e($old['phone']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="username">Username</label>
                        <input class="form-control" id="username" name="username" required maxlength="50" value="<?= e($old['username']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" required maxlength="150" value="<?= e($old['email']) ?>">
                    </div>
                </div>

                <hr class="my-4">

                <h2 class="h6">Change Password <span class="text-body-secondary fw-normal">(optional)</span></h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="new_password">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" minlength="8">
                        <div class="form-text">Leave blank to keep your current password.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="new_password_confirm">Confirm New Password</label>
                        <input type="password" class="form-control" id="new_password_confirm" name="new_password_confirm" minlength="8">
                    </div>
                </div>

                <hr class="my-4">

                <div class="mb-3">
                    <label class="form-label" for="current_password">Current Password</label>
                    <input type="password" class="form-control" id="current_password" name="current_password">
                    <div class="form-text">Required only when changing your username, email, or password.</div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
