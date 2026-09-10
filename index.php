<?php
require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Welcome';
require __DIR__ . '/includes/header.php';
?>

<div class="hero">
    <h1 class="display-6">How can we help?</h1>
    <p>Submit a support ticket and our helpdesk team will follow up, or log in if you're a helpdesk agent.</p>
</div>

<div class="row g-4 justify-content-center form-shell">
    <div class="col-sm-6">
        <a href="submit-ticket.php" class="card option-card p-4">
            <h2 class="h5 mb-2">Submit a Ticket</h2>
            <p class="text-body-secondary mb-0">No account needed. Tell us what's going on and we'll take it from there.</p>
        </a>
    </div>
    <div class="col-sm-6">
        <a href="login.php" class="card option-card p-4">
            <h2 class="h5 mb-2">Helpdesk Login</h2>
            <p class="text-body-secondary mb-0">For helpdesk staff to view, manage, and resolve tickets.</p>
        </a>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
