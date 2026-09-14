<?php
require __DIR__ . '/includes/bootstrap.php';

// Only meaningful once config exists and the base schema is in place —
// otherwise there's nothing to migrate yet, so send them to the installer.
if (!$configExists) {
    header('Location: install.php');
    exit;
}

try {
    if (!schema_installed()) {
        header('Location: install.php');
        exit;
    }
    $pending = pending_migrations();
} catch (PDOException $e) {
    http_response_code(500);
    exit('Could not connect to the database. Check the credentials in config/config.php.');
}

$errors = [];
$applied = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pending) {
    if (!verify_csrf()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        try {
            foreach ($pending as $migration) {
                run_migration($migration['file']);
                $applied[] = $migration['label'];
            }
            $pending = pending_migrations();
        } catch (PDOException $e) {
            $errors[] = 'Migration failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Database Migrations';
require __DIR__ . '/includes/header.php';
?>

<div class="form-shell">
    <div class="mb-4">
        <h1 class="h3">Database Migrations</h1>
        <p class="text-body-secondary">Bring an existing database up to date after pulling new code — no <code>mysql</code> client needed.</p>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($applied): ?>
        <div class="alert alert-success">
            <p class="mb-1">Applied:</p>
            <ul class="mb-0">
                <?php foreach ($applied as $label): ?>
                    <li><?= e($label) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!$pending): ?>
        <div class="card">
            <div class="card-body p-4 text-center">
                <p class="mb-3">The database is already up to date.</p>
                <a href="login.php" class="btn btn-primary">Go to Login</a>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body p-4">
                <p>The following migrations haven't been applied yet:</p>
                <ul>
                    <?php foreach ($pending as $migration): ?>
                        <li><code><?= e($migration['file']) ?></code> — <?= e($migration['label']) ?></li>
                    <?php endforeach; ?>
                </ul>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="d-grid mt-3">
                        <button type="submit" class="btn btn-primary">Run Migrations</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
