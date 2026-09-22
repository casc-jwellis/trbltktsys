<?php
/**
 * @var array|null $smtpSettings
 * @var array|null $imapSettings
 * @var array      $users
 */
$myEmail = array_column($users, 'email', 'id')[current_user_id()] ?? '';
?>

<?php if (!smtp_settings_supported()): ?>
    <div class="alert alert-warning">The database is out of date — email settings are unavailable until an administrator visits <a href="migrate.php">migrate.php</a>.</div>
<?php else: ?>

<div class="card mb-4">
    <div class="card-header">Outbound Mail (SMTP)</div>
    <div class="card-body p-4">
        <p class="text-body-secondary">Currently only SMTP sending is supported. This has been tested with Gmail using an app password.</p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_smtp_settings">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="smtp_host">SMTP Host</label>
                    <input class="form-control" id="smtp_host" name="host" required maxlength="150"
                           placeholder="smtp.example.com" value="<?= e($smtpSettings['host']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="smtp_port">Port</label>
                    <input type="number" class="form-control" id="smtp_port" name="port" required min="1" max="65535"
                           value="<?= (int) $smtpSettings['port'] ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="smtp_encryption">Encryption</label>
                    <select class="form-select" id="smtp_encryption" name="encryption">
                        <?php foreach (['none' => 'None', 'tls' => 'STARTTLS', 'ssl' => 'SSL/TLS'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $smtpSettings['encryption'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="smtp_username">Username <span class="text-body-secondary">(optional)</span></label>
                    <input class="form-control" id="smtp_username" name="username" maxlength="150" value="<?= e($smtpSettings['username']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="smtp_password">Password</label>
                    <input type="password" class="form-control" id="smtp_password" name="password">
                    <div class="form-text">Leave blank to keep the current password.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="smtp_from_email">"From" Email Address</label>
                    <input type="email" class="form-control" id="smtp_from_email" name="from_email" required maxlength="150"
                           value="<?= e($smtpSettings['from_email']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="smtp_from_name">"From" Name <span class="text-body-secondary">(optional)</span></label>
                    <input class="form-control" id="smtp_from_name" name="from_name" maxlength="100" value="<?= e($smtpSettings['from_name']) ?>">
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Save Email Settings</button>
            </div>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">Send Test Email</div>
    <div class="card-body p-4">
        <p class="text-body-secondary">Sends a short test message using the settings above, to confirm they actually work.</p>
        <form method="post" class="row g-3 align-items-end" data-loading-text="Sending...">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="send_test_email">
            <div class="col-md-8">
                <label class="form-label" for="test_email">Send To</label>
                <input type="email" class="form-control" id="test_email" name="test_email" required maxlength="150"
                       value="<?= e($myEmail) ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-outline-primary">Send Test Email</button>
            </div>
        </form>
    </div>
</div>

<?php endif; ?>

<?php if (!imap_settings_supported()): ?>
    <div class="alert alert-warning">The database is out of date — inbound mail settings are unavailable until an administrator visits <a href="migrate.php">migrate.php</a>.</div>
<?php else: ?>

<div class="card mb-4">
    <div class="card-header">Inbound Mail (IMAP)</div>
    <div class="card-body p-4">
        <p class="text-body-secondary">
            Pulls in submitter replies sent directly to this mailbox instead of through the ticket link, via a
            scheduled run of <code>bin/imap-poll.php</code> (there's no built-in scheduler — set that up separately,
            e.g. Task Scheduler or cron). STARTTLS isn't supported yet — use SSL/TLS (implicit, typically port 993)
            or plaintext on a trusted network. <strong>Processed</strong> and <strong>Unmatched</strong> must already
            exist as folders in the mailbox.
        </p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_imap_settings">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="imap_enabled" name="imap_enabled" <?= $imapSettings['enabled'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="imap_enabled">Enabled</label>
            </div>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="imap_host">IMAP Host</label>
                    <input class="form-control" id="imap_host" name="imap_host" required maxlength="150"
                           placeholder="imap.example.com" value="<?= e($imapSettings['host']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="imap_port">Port</label>
                    <input type="number" class="form-control" id="imap_port" name="imap_port" required min="1" max="65535"
                           value="<?= (int) $imapSettings['port'] ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="imap_encryption">Encryption</label>
                    <select class="form-select" id="imap_encryption" name="imap_encryption">
                        <?php foreach (['ssl' => 'SSL/TLS', 'none' => 'None'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $imapSettings['encryption'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="imap_username">Username</label>
                    <input class="form-control" id="imap_username" name="imap_username" required maxlength="150" value="<?= e($imapSettings['username']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="imap_password">Password</label>
                    <input type="password" class="form-control" id="imap_password" name="imap_password">
                    <div class="form-text">Leave blank to keep the current password.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="imap_mailbox">Mailbox to Check</label>
                    <input class="form-control" id="imap_mailbox" name="imap_mailbox" required maxlength="150" value="<?= e($imapSettings['mailbox']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="imap_processed_mailbox">Processed Folder</label>
                    <input class="form-control" id="imap_processed_mailbox" name="imap_processed_mailbox" maxlength="150" value="<?= e($imapSettings['processed_mailbox']) ?>">
                    <div class="form-text">Where matched replies are moved. Leave blank to leave them in place.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="imap_unmatched_mailbox">Unmatched Folder</label>
                    <input class="form-control" id="imap_unmatched_mailbox" name="imap_unmatched_mailbox" maxlength="150" value="<?= e($imapSettings['unmatched_mailbox']) ?>">
                    <div class="form-text">Where messages that couldn't be matched to a ticket are moved.</div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Save Inbound Mail Settings</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">Test IMAP Connection</div>
    <div class="card-body p-4">
        <p class="text-body-secondary">Connects using the settings above and checks the configured mailbox, without processing anything.</p>
        <form method="post" data-loading-text="Connecting...">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="test_imap_connection">
            <button type="submit" class="btn btn-outline-primary">Test Connection</button>
        </form>
    </div>
</div>

<?php endif; ?>
