<?php
/** @var ?array $googleSettings */

$problem = GoogleIdToken::problem();
$siteUrl = parse_url(base_url());
$origin = $siteUrl['scheme'] . '://' . $siteUrl['host'] . (isset($siteUrl['port']) ? ':' . $siteUrl['port'] : '');
$isHttps = $siteUrl['scheme'] === 'https';
$noEmail = google_signin_supported()
    ? (int) db()->query("SELECT COUNT(*) FROM users WHERE (email IS NULL OR email = '') AND disabled = 0")->fetchColumn()
    : 0;
?>

<?php if (!google_signin_supported()): ?>
    <div class="alert alert-warning">The database is out of date — Sign in with Google is unavailable until an administrator runs the pending updates on the <a href="admin-settings.php?tab=database">Database tab</a>.</div>
<?php else: ?>

<?php if ($problem !== null): ?>
    <div class="alert alert-warning"><?= e($problem) ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">Sign in with Google</div>
    <div class="card-body p-4">
        <p class="text-body-secondary">
            Lets staff sign in with their Google account instead of a password. Passwords keep working too, which is your
            way in if Google is ever unavailable.
        </p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_google_signin">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="google_enabled" name="google_enabled" <?= $googleSettings['enabled'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="google_enabled">Show a "Sign in with Google" button on the login page</label>
            </div>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="google_client_id">Google Client ID</label>
                    <input class="form-control" id="google_client_id" name="google_client_id" maxlength="150" spellcheck="false" autocapitalize="off"
                           placeholder="1234567890-abcdefg.apps.googleusercontent.com" value="<?= e($googleSettings['client_id']) ?>">
                    <div class="form-text">From the Google Cloud console (steps below). It is not a secret.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="google_allowed_domain">Allowed Email Domain</label>
                    <div class="input-group">
                        <span class="input-group-text">@</span>
                        <input class="form-control" id="google_allowed_domain" name="google_allowed_domain" required maxlength="100" spellcheck="false" autocapitalize="off"
                               value="<?= e($googleSettings['allowed_domain']) ?>">
                    </div>
                    <div class="form-text">Only Google Workspace accounts on this domain can sign in.</div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Save Sign-in Settings</button>
            </div>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">How it works</div>
    <div class="card-body p-4">
        <p class="mb-2">
            A person gets in only if their Google account is on the allowed domain, Google has confirmed the address is
            theirs, and exactly one active helpdesk account uses that same email address (capitals ignored). Nobody is created
            automatically: add the address to the account in User Management first. Disabled accounts stay locked out, and
            roles and groups are unchanged.
        </p>
        <?php if ($noEmail > 0): ?>
            <p class="mb-2"><strong><?= $noEmail ?></strong> active account<?= $noEmail === 1 ? ' has' : 's have' ?> no email address and can't use Google sign-in until one is added.</p>
        <?php endif; ?>
        <p class="mb-0 text-body-secondary">
            Someone still on an emailed temporary password who signs in with Google no longer needs it: it is switched off.
            If they ever need to sign in without Google, they can choose a password with "Forgot password?" on the login page
            (when email is set up), or you can set them one in User Management.
        </p>
    </div>
</div>

<div class="card">
    <div class="card-header">Setting it up in Google</div>
    <div class="card-body p-4">
        <ol class="mb-0">
            <li class="mb-2">Open the <strong>Google Cloud console → APIs &amp; Services</strong> (console.cloud.google.com) and pick or create a project.</li>
            <li class="mb-2"><strong>OAuth consent screen</strong>: choose <em>Internal</em> if your Google Workspace allows it (only your own domain's accounts can sign in, and Google doesn't need to review anything). Fill in the app name and a support email, and set the <em>privacy policy</em> link to <code><?= e(base_url()) ?>/privacy.php</code>.</li>
            <li class="mb-2"><strong>Credentials → Create credentials → OAuth client ID</strong>, type <em>Web application</em>.</li>
            <li class="mb-2">Under <strong>Authorized JavaScript origins</strong> add <code><?= e($origin) ?></code> (the address people use to open this site, with no path; add each address if there is more than one, such as a test server).
                <?php if (!$isHttps): ?><span class="text-body-secondary">Google only accepts <code>http://</code> origins for <code>localhost</code>; any other address must be <code>https://</code>.</span><?php endif; ?>
                No redirect URI is needed.</li>
            <li>Copy the <strong>Client ID</strong> (not the secret) into the box above, turn the switch on and save.</li>
        </ol>
    </div>
</div>

<?php endif; ?>
