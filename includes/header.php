<?php
/** @var string $pageTitle */
$pageTitle ??= app_name();
$user = current_user();
$serverTheme = $user ? current_user_theme() : null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
        // Set the theme before first paint, so there's no flash of the wrong
        // theme -- assets/js/theme-toggle.js only handles the click to change
        // it afterwards. A logged-in agent's saved preference (from the
        // server, so it follows them to another browser/device) wins over
        // this browser's localStorage, which in turn wins over the OS
        // preference, used until anyone picks explicitly.
        (function () {
            var serverTheme = <?= $serverTheme !== null ? json_encode($serverTheme) : 'null' ?>;
            try {
                if (serverTheme === 'light' || serverTheme === 'dark') {
                    document.documentElement.setAttribute('data-bs-theme', serverTheme);
                    localStorage.setItem('theme', serverTheme);
                    return;
                }
                var stored = localStorage.getItem('theme');
                var theme = (stored === 'light' || stored === 'dark')
                    ? stored
                    : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.setAttribute('data-bs-theme', theme);
            } catch (e) {
                document.documentElement.setAttribute('data-bs-theme', serverTheme === 'dark' ? 'dark' : 'light');
            }
        })();
    </script>
    <title><?= e($pageTitle) ?> · <?= e(app_name()) ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-md app-navbar">
    <div class="container">
        <a class="navbar-brand" href="<?= $user ? 'dashboard.php' : 'index.php' ?>">
            <span class="brand-mark">TT</span> <?= e(app_name()) ?>
        </a>
        <div class="d-flex align-items-center order-md-last gap-2 ms-md-3">
            <button
                type="button"
                id="themeToggle"
                class="btn btn-outline-secondary btn-sm theme-toggle"
                title="Switch to dark theme"
                aria-label="Switch to dark theme"
                data-csrf="<?= e(csrf_token()) ?>"
            >
                <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                    <path d="M6 .278a.768.768 0 0 1 .08.858 7.208 7.208 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277.527 0 1.04-.055 1.533-.16a.787.787 0 0 1 .81.316.733.733 0 0 1-.031.893A8.349 8.349 0 0 1 8.344 16C3.734 16 0 12.286 0 7.7 0 4.421 1.945 1.616 4.723.278a.75.75 0 0 1 .81.083A.717.717 0 0 1 6 .278Z"/>
                </svg>
                <svg class="icon-sun" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                    <path d="M8 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0Zm0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2a.5.5 0 0 1 .5-.5Zm8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5ZM3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8Zm10.657-5.657a.5.5 0 0 1 0 .707l-1.414 1.415a.5.5 0 1 1-.707-.708l1.414-1.414a.5.5 0 0 1 .707 0Zm-9.193 9.193a.5.5 0 0 1 0 .707L3.05 13.657a.5.5 0 0 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0Zm9.193 2.121a.5.5 0 0 1-.707 0l-1.414-1.414a.5.5 0 0 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707ZM4.464 4.465a.5.5 0 0 1-.707 0L2.343 3.05a.5.5 0 1 1 .707-.707L4.464 3.757a.5.5 0 0 1 0 .708Z"/>
                </svg>
            </button>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav ms-auto align-items-md-center gap-md-2">
                <?php if ($user): ?>
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Tickets</a></li>
                    <?php if (is_admin()): ?>
                        <li class="nav-item"><a class="nav-link" href="admin-settings.php">Admin Settings</a></li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2" href="account.php" title="My Account">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                                <circle cx="8" cy="5" r="3"/>
                                <path d="M2.5 14c0-3 2.5-5 5.5-5s5.5 2 5.5 5a.5.5 0 0 1-.5.5H3a.5.5 0 0 1-.5-.5Z"/>
                            </svg>
                            <?= e($user['full_name']) ?>
                        </a>
                    </li>
                    <li class="nav-item"><a class="btn btn-outline-secondary btn-sm" href="logout.php">Log out</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="submit-ticket.php">Submit a Ticket</a></li>
                    <li class="nav-item"><a class="btn btn-primary btn-sm" href="login.php">Helpdesk Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<main class="py-4 py-md-5">
    <div class="container">
        <?php foreach (['success', 'error'] as $flashKey): ?>
            <?php if ($msg = flash($flashKey)): ?>
                <div class="alert alert-<?= $flashKey === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                    <?= e($msg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
