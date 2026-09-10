<?php
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (attempt_login($username, $password)) {
            header('Location: dashboard.php');
            exit;
        }

        $error = 'Invalid username or password.';
    }
}

$pageTitle = 'Helpdesk Login';
require __DIR__ . '/includes/header.php';
?>

<div class="auth-shell">
    <div class="mb-4 text-center">
        <h1 class="h3">Helpdesk Login</h1>
        <p class="text-body-secondary">Staff access to the ticket queue.</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-4">
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="username">Username</label>
                    <input class="form-control" id="username" name="username" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">Log In</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
