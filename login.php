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

        try {
            $result = attempt_login($username, $password);
        } catch (PDOException $e) {
            $result = 'schema-error';
        }

        if ($result === 'ok') {
            header('Location: dashboard.php');
            exit;
        }

        $error = match ($result) {
            'disabled'     => 'This account has been disabled. Contact an administrator.',
            'schema-error' => 'The database is out of date. An administrator should run the pending database updates (Admin Settings, Database tab).',
            default        => 'Invalid username or password.',
        };
    }
}

// The button is shown only when an admin has turned it on and it can work on
// this server. The nonce goes inside the signed token Google returns, and
// login-google.php only accepts a token carrying the nonce of the session it
// arrives in -- so a token can't be replayed or planted from another visitor.
require_once __DIR__ . '/includes/mail.php';
require_once __DIR__ . '/includes/password-resets.php';
require_once __DIR__ . '/includes/google-signin.php';
$googleClientId = google_signin_client_id(); // null before migration 024 is applied
$googleNonce = null;
if ($googleClientId !== null) {
    if (empty($_SESSION['google_nonce'])) {
        $_SESSION['google_nonce'] = bin2hex(random_bytes(16));
    }
    $googleNonce = $_SESSION['google_nonce'];
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

            <?php if (password_resets_available()): ?>
                <p class="text-center mt-3 mb-0"><a href="forgot-password.php" class="text-decoration-none small">Forgot password?</a></p>
            <?php endif; ?>

            <?php if ($googleClientId !== null): ?>
                <div class="d-flex align-items-center gap-3 my-4 text-body-secondary small">
                    <hr class="flex-grow-1 m-0"><span>or</span><hr class="flex-grow-1 m-0">
                </div>
                <?php // Google's script turns these two divs into the button; when it's used, assets/js/google-signin.js posts the signed token to login-google.php. ?>
                <div class="google-signin d-flex justify-content-center">
                    <div id="g_id_onload" data-client_id="<?= e($googleClientId) ?>" data-callback="googleSignIn"
                         data-nonce="<?= e($googleNonce) ?>" data-auto_prompt="false"></div>
                    <div class="g_id_signin" data-type="standard" data-theme="outline" data-size="large" data-shape="rectangular"
                         data-text="signin_with" data-logo_alignment="left" data-width="280"></div>
                </div>
                <form method="post" action="login-google.php" id="google-form" hidden>
                    <?= csrf_field() ?>
                    <input type="hidden" name="credential" value="">
                </form>
                <script src="<?= asset_url('assets/js/google-signin.js') ?>"></script>
                <script src="https://accounts.google.com/gsi/client" async></script>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
