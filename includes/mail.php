<?php
// SMTP settings and mail sending, via the vendored PHPMailer library in
// lib/phpmailer (no Composer -- see lib/phpmailer/README or the project
// README for where these files came from).
require_once __DIR__ . '/../lib/phpmailer/Exception.php';
require_once __DIR__ . '/../lib/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../lib/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

const SMTP_ENCRYPTIONS = ['none', 'tls', 'ssl'];

/** Whether the smtp_settings table exists yet (migration 013). */
function smtp_settings_supported(): bool
{
    static $result = null;
    if ($result === null) {
        $result = table_exists('smtp_settings');
    }
    return $result;
}

/**
 * The single SMTP settings row, or defaults if the table exists but the seed
 * row is somehow missing. Null only when the table itself doesn't exist yet
 * (pending migration).
 */
function get_smtp_settings(): ?array
{
    if (!smtp_settings_supported()) {
        return null;
    }
    $stmt = db()->query('SELECT * FROM smtp_settings WHERE id = 1');
    $row = $stmt->fetch();
    return $row ?: [
        'host' => '', 'port' => 587, 'encryption' => 'tls',
        'username' => '', 'password' => null, 'from_email' => '', 'from_name' => '',
    ];
}

/**
 * Updates the single SMTP settings row. $password may be null to leave the
 * currently stored password unchanged (mirrors the "leave blank to keep"
 * pattern used for user account passwords).
 */
function save_smtp_settings(
    string $host,
    int $port,
    string $encryption,
    string $username,
    ?string $password,
    string $fromEmail,
    string $fromName
): void {
    if ($password !== null) {
        $stmt = db()->prepare(
            'UPDATE smtp_settings SET host = ?, port = ?, encryption = ?, username = ?, password = ?, from_email = ?, from_name = ? WHERE id = 1'
        );
        $stmt->execute([$host, $port, $encryption, $username, $password, $fromEmail, $fromName]);
    } else {
        $stmt = db()->prepare(
            'UPDATE smtp_settings SET host = ?, port = ?, encryption = ?, username = ?, from_email = ?, from_name = ? WHERE id = 1'
        );
        $stmt->execute([$host, $port, $encryption, $username, $fromEmail, $fromName]);
    }
}

/** Builds a PHPMailer instance configured from the stored SMTP settings. */
function configured_mailer(): PHPMailer
{
    $settings = get_smtp_settings();
    if (!$settings || $settings['host'] === '' || !$settings['from_email']) {
        throw new PHPMailerException('SMTP settings are not fully configured yet.');
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $settings['host'];
    $mail->Port = (int) $settings['port'];

    if ($settings['username'] !== null && $settings['username'] !== '') {
        $mail->SMTPAuth = true;
        $mail->Username = $settings['username'];
        $mail->Password = (string) $settings['password'];
    } else {
        $mail->SMTPAuth = false;
    }

    if ($settings['encryption'] === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($settings['encryption'] === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $mail->SMTPSecure = '';
        $mail->SMTPAutoTLS = false;
    }

    $mail->setFrom($settings['from_email'], $settings['from_name'] ?: '');

    return $mail;
}

/**
 * Sends a plain-text test message to $toEmail using the stored SMTP
 * settings. Throws PHPMailer\PHPMailer\Exception with a human-readable
 * reason on failure.
 */
function send_test_email(string $toEmail): void
{
    $mail = configured_mailer();
    $mail->addAddress($toEmail);
    $appName = app_name();
    $mail->Subject = 'Test email from ' . $appName;
    $mail->Body = "This is a test email from {$appName}, sent to confirm the SMTP settings in Admin Settings are working.";
    $mail->send();
}
