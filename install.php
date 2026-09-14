<?php
require __DIR__ . '/includes/bootstrap.php';

// bootstrap.php already redirects here whenever setup is incomplete, and
// leaves this script alone otherwise — so if we're fully installed, there's
// nothing left for this page to do.
if ($configExists) {
    try {
        if (is_installed()) {
            header('Location: login.php');
            exit;
        }
    } catch (PDOException $e) {
        // handled below via $dbError
    }
}

$errors = [];
$dbError = null;
$schemaReady = false;

if ($configExists) {
    try {
        $schemaReady = schema_installed();
    } catch (PDOException $e) {
        $dbError = 'Could not connect to the database with the credentials in config/config.php: ' . $e->getMessage();
    }
}

$stage = !$configExists ? 'config' : ($dbError ? 'db-error' : (!$schemaReady ? 'schema' : 'user'));

$old = [
    'db_host'   => '127.0.0.1',
    'db_port'   => '',
    'db_name'   => '',
    'db_user'   => '',
    'app_name'  => 'Helpdesk',
    'timezone'  => 'America/Chicago',
    'username'  => '',
    'full_name' => '',
    'email'     => '',
    'phone'     => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['stage'] ?? '') === $stage) {
    if (!verify_csrf()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif ($stage === 'config') {
        foreach (['db_host', 'db_port', 'db_name', 'db_user', 'app_name', 'timezone'] as $field) {
            $old[$field] = trim((string) ($_POST[$field] ?? ''));
        }
        $dbPass = (string) ($_POST['db_pass'] ?? '');

        if ($old['db_host'] === '') {
            $errors[] = 'Please enter a database host.';
        }
        if ($old['db_name'] === '') {
            $errors[] = 'Please enter a database name.';
        }
        if ($old['db_user'] === '') {
            $errors[] = 'Please enter a database username.';
        }
        if ($old['app_name'] === '') {
            $old['app_name'] = 'Helpdesk';
        }
        if (!in_array($old['timezone'], DateTimeZone::listIdentifiers(), true)) {
            $errors[] = 'Please choose a valid timezone.';
        }

        if (!$errors) {
            $dsn = 'mysql:host=' . $old['db_host']
                . ($old['db_port'] !== '' ? ';port=' . $old['db_port'] : '')
                . ';dbname=' . $old['db_name']
                . ';charset=utf8mb4';

            try {
                new PDO($dsn, $old['db_user'], $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            } catch (PDOException $e) {
                $errors[] = 'Could not connect with those database credentials: ' . $e->getMessage();
            }
        }

        if (!$errors) {
            try {
                write_config(
                    ['dsn' => $dsn, 'user' => $old['db_user'], 'pass' => $dbPass],
                    ['name' => $old['app_name'], 'timezone' => $old['timezone']]
                );
                header('Location: install.php');
                exit;
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
    } elseif ($stage === 'schema') {
        try {
            install_schema();
            header('Location: install.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Could not create the database tables: ' . $e->getMessage();
        }
    } elseif ($stage === 'user') {
        $old['username'] = trim((string) ($_POST['username'] ?? ''));
        $old['full_name'] = trim((string) ($_POST['full_name'] ?? ''));
        $old['email'] = trim((string) ($_POST['email'] ?? ''));
        $old['phone'] = trim((string) ($_POST['phone'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        if ($old['username'] === '') {
            $errors[] = 'Please enter a username.';
        }
        if ($old['full_name'] === '') {
            $errors[] = 'Please enter your full name.';
        }
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (strlen($old['phone']) > 30) {
            $errors[] = 'Phone number is too long (30 characters max).';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $passwordConfirm) {
            $errors[] = 'Passwords do not match.';
        }

        if (!$errors) {
            // The first account is an administrator so it can access Admin
            // Settings right away; every account can already manage tickets.
            $stmt = db()->prepare(
                'INSERT INTO users (username, password_hash, full_name, email, phone, is_admin) VALUES (?, ?, ?, ?, ?, 1)'
            );
            $stmt->execute([
                $old['username'],
                password_hash($password, PASSWORD_DEFAULT),
                $old['full_name'],
                $old['email'],
                $old['phone'] !== '' ? $old['phone'] : null,
            ]);

            flash('success', 'Helpdesk account created. Log in below.');
            header('Location: login.php');
            exit;
        }
    }
}

$pageTitle = 'Set Up Helpdesk';
require __DIR__ . '/includes/header.php';
?>

<div class="form-shell">
    <div class="mb-4">
        <h1 class="h3">Set Up <?= e(app_name()) ?></h1>
        <p class="text-body-secondary">Looks like this is a fresh install. Let's get things ready.</p>
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

    <?php if ($dbError): ?>
        <div class="alert alert-danger"><?= e($dbError) ?></div>
        <p class="text-body-secondary">Fix the <code>db</code> settings in <code>config/config.php</code> (or delete the file to run through setup again), then reload this page.</p>

    <?php elseif ($stage === 'config'): ?>
        <div class="card">
            <div class="card-body p-4">
                <p class="text-body-secondary">Enter the MySQL database this installation should use. We'll test the connection before saving anything.</p>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="stage" value="config">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="db_host">Database Host</label>
                            <input class="form-control" id="db_host" name="db_host" required value="<?= e($old['db_host']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="db_port">Database Port <span class="text-body-secondary">(optional)</span></label>
                            <input class="form-control" id="db_port" name="db_port" placeholder="3306" value="<?= e($old['db_port']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="db_name">Database Name</label>
                            <input class="form-control" id="db_name" name="db_name" required value="<?= e($old['db_name']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="db_user">Database Username</label>
                            <input class="form-control" id="db_user" name="db_user" required value="<?= e($old['db_user']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="db_pass">Database Password</label>
                            <input type="password" class="form-control" id="db_pass" name="db_pass">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="app_name">Helpdesk Name</label>
                            <input class="form-control" id="app_name" name="app_name" value="<?= e($old['app_name']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="timezone">Timezone</label>
                            <input class="form-control" id="timezone" name="timezone" list="timezone-list" value="<?= e($old['timezone']) ?>">
                            <datalist id="timezone-list">
                                <?php foreach (DateTimeZone::listIdentifiers() as $tz): ?>
                                    <option value="<?= e($tz) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary">Test Connection &amp; Save</button>
                    </div>
                </form>
            </div>
        </div>

    <?php elseif ($stage === 'schema'): ?>
        <div class="card">
            <div class="card-body p-4">
                <p>Connected to the database. The ticket and staff-account tables haven't been created yet.</p>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="stage" value="schema">
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Create Database Tables</button>
                    </div>
                </form>
            </div>
        </div>

    <?php else: ?>
        <div class="card">
            <div class="card-body p-4">
                <p class="text-body-secondary">Create the first helpdesk staff account. You'll use this to log in and manage tickets.</p>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="stage" value="user">
                    <div class="mb-3">
                        <label class="form-label" for="full_name">Full Name</label>
                        <input class="form-control" id="full_name" name="full_name" required value="<?= e($old['full_name']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="username">Username</label>
                        <input class="form-control" id="username" name="username" required value="<?= e($old['username']) ?>">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required value="<?= e($old['email']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Phone <span class="text-body-secondary">(optional)</span></label>
                            <input class="form-control" id="phone" name="phone" maxlength="30" value="<?= e($old['phone']) ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required minlength="8">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password_confirm">Confirm Password</label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="8">
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary">Create Account</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
