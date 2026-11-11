<?php
// The privacy policy. Public (no login), so Google's consent screen and anyone
// submitting a ticket can read it. Change PRIVACY_UPDATED whenever the text changes.
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/mail.php';

const PRIVACY_UPDATED = 'November 10, 2026';

// The helpdesk's own address, if outgoing mail is set up.
$smtp = smtp_settings_supported() ? get_smtp_settings() : null;
$contact = trim((string) ($smtp['from_email'] ?? ''));

$pageTitle = 'Privacy Policy';
require __DIR__ . '/includes/header.php';
?>

<div class="card mx-auto" style="max-width: 760px;">
    <div class="card-body p-4 p-md-5">
        <h1 class="h3">Privacy policy</h1>
        <p class="text-body-secondary small">For <?= e(app_name()) ?> &middot; last updated <?= e(PRIVACY_UPDATED) ?></p>

        <p><strong>We do not share your information with anyone, and we have no intention of ever doing so.</strong>
            We do not sell it, rent it, trade it or hand it to advertisers, partners or other organizations. The only
            exception would be where the law requires us to disclose something.</p>

        <h2 class="h5 mt-4">What this site is</h2>
        <p><?= e(app_name()) ?> is a helpdesk. Anyone can submit a trouble ticket; helpdesk staff have accounts and use it to
            read and answer tickets.</p>

        <h2 class="h5 mt-4">What we keep</h2>
        <ul>
            <li><strong>When you submit a ticket:</strong> your name, email address and phone number; the ticket's subject,
                category, priority and description; any screenshot or file you attach; and the replies sent back and
                forth, by web or by email. We also keep when each of these happened.</li>
            <li><strong>Helpdesk staff accounts:</strong> name, username, email address, phone number, the groups the person
                belongs to, whether they are an administrator, and a theme preference. A password is stored only as a
                one-way scramble that nobody, including us, can turn back into the password.</li>
            <li><strong>Email we send you</strong> about your ticket, and any email you send back to the helpdesk's address.</li>
            <li><strong>Cookies:</strong> one short-lived session cookie that keeps a page working and keeps a staff member
                signed in. Your light/dark choice is remembered in your browser. There are no analytics or advertising
                cookies.</li>
        </ul>

        <h2 class="h5 mt-4">Signing in with Google</h2>
        <p>Staff can choose <em>Sign in with Google</em>. Google then confirms to this site that they own their
            email address, and this site uses <strong>only that email address</strong> to find their existing account. We do not
            ask Google for access to email, contacts, calendar, files or anything else in the Google account, and we do not
            store anything else Google sends. Google's handling of the sign-in is covered by
            <a href="https://policies.google.com/privacy" rel="noopener noreferrer">Google's privacy policy</a>.</p>

        <h2 class="h5 mt-4">Services that load with the pages</h2>
        <p>To display, these pages load the Bootstrap stylesheet from <code>cdn.jsdelivr.net</code> and the Inter font from
            Google Fonts, and the staff login page loads Google's sign-in button when that is turned on. Fetching those means
            your browser contacts those services, as it does for any web page that uses them. We send them none of the
            information described above.</p>

        <h2 class="h5 mt-4">How we use it</h2>
        <p>Only to run the helpdesk: to answer your ticket, to show staff the tickets they are allowed to see, to keep a
            record of who did what, and to send the email about a ticket or an account. We do not use it for advertising,
            tracking or profiling.</p>

        <h2 class="h5 mt-4">Who can see it</h2>
        <p>Helpdesk staff, within the limits of the groups they belong to, and the administrators of this site. Nobody
            outside the organization.</p>

        <h2 class="h5 mt-4">How long we keep it</h2>
        <p>Tickets are kept for as long as they are needed; an administrator can delete them. A staff member's details stay
            for as long as their account exists.</p>

        <h2 class="h5 mt-4">Your choices</h2>
        <p>You can ask the helpdesk what we hold about you, to correct it, or to delete your tickets.</p>

        <?php if ($contact !== ''): ?>
            <h2 class="h5 mt-4">Contact</h2>
            <p>Questions about this policy or your information: <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a>.</p>
        <?php endif; ?>

        <h2 class="h5 mt-4">Changes</h2>
        <p>If this policy changes, the date at the top changes with it. Our promise not to share your information (apart from
            what the law requires) will not.</p>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
