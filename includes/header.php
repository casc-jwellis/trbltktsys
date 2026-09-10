<?php
/** @var string $pageTitle */
$pageTitle ??= app_name();
$user = current_user();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e(app_name()) ?></title>
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
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav ms-auto align-items-md-center gap-md-2">
                <?php if ($user): ?>
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Tickets</a></li>
                    <li class="nav-item"><span class="nav-link text-body-secondary">Hi, <?= e($user['full_name']) ?></span></li>
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
