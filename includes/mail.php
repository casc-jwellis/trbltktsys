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
 * Builds a mailer from the stored settings and sends one plain-text email.
 * Throws PHPMailer\PHPMailer\Exception with a human-readable reason on
 * failure (including when SMTP isn't configured yet) -- callers decide
 * whether that should block the surrounding action, surface a warning, or
 * just be logged.
 *
 * When $ticketId is given, every email about that ticket shares one
 * Message-ID (see ticket_thread_message_id()) so mail clients thread them
 * together: the first one ($isRoot) sends it as its own Message-ID, and
 * every later one references it via In-Reply-To/References -- the same
 * mechanism a real reply chain uses, just synthesized since these emails
 * usually aren't actual replies to each other (e.g. a submitter's
 * confirmation and the agent notification fire independently, but should
 * still land in one thread per recipient's mailbox).
 */
function send_ticket_email(string $toEmail, string $subject, string $body, ?int $ticketId = null, bool $isRoot = false): void
{
    $mail = configured_mailer();
    $mail->addAddress($toEmail);
    $mail->Subject = $subject;
    $mail->Body = $body;

    if ($ticketId !== null) {
        $anchor = ticket_thread_message_id($ticketId);
        if ($isRoot) {
            $mail->MessageID = $anchor;
        } else {
            $mail->addCustomHeader('In-Reply-To', $anchor);
            $mail->addCustomHeader('References', $anchor);
        }
    }

    $mail->send();
}

/**
 * Sends a plain-text test message to $toEmail using the stored SMTP
 * settings. Throws PHPMailer\PHPMailer\Exception with a human-readable
 * reason on failure.
 */
function send_test_email(string $toEmail): void
{
    $appName = app_name();
    send_ticket_email(
        $toEmail,
        'Test email from ' . $appName,
        "This is a test email from {$appName}, sent to confirm the SMTP settings in Admin Settings are working."
    );
}

/**
 * Gmail (unlike most other mail clients) only groups messages into one
 * conversation when the Subject also matches, in addition to
 * References/In-Reply-To -- so every email about a ticket must share this
 * exact subject verbatim. Anything distinguishing one email from another
 * (e.g. "new reply", "update") belongs in the body instead.
 */
function ticket_email_subject(int $ticketId, string $ticketSubject): string
{
    return 'Ticket #' . $ticketId . ': ' . $ticketSubject;
}

/**
 * Sent once, right after a ticket is submitted. This is the root of the
 * ticket's email thread -- see send_ticket_email().
 */
function send_ticket_confirmation_email(array $ticket): void
{
    $link = ticket_public_link($ticket['public_token']);
    $subject = ticket_email_subject((int) $ticket['id'], $ticket['subject']);
    $body = "Hi {$ticket['requester_name']},\n\n"
        . "We've received your ticket and will get back to you soon.\n\n"
        . "You can check its status, see any responses, and add additional comments at any time:\n{$link}\n";
    send_ticket_email($ticket['requester_email'], $subject, $body, (int) $ticket['id'], true);
}

/**
 * Emails every active agent in a ticket's assigned groups. Best-effort: a
 * failure for one recipient is logged and doesn't stop the others, and the
 * caller never sees an exception here -- these are background notifications,
 * not something the (anonymous, unauthenticated) submitter should see fail.
 */
function notify_ticket_agents(int $ticketId, string $subject, string $body): void
{
    foreach (ticket_assigned_agent_emails($ticketId) as $recipient) {
        try {
            send_ticket_email($recipient['email'], $subject, $body, $ticketId);
        } catch (Throwable $e) {
            error_log("Failed to notify {$recipient['email']} about ticket #{$ticketId}: " . $e->getMessage());
        }
    }
}

/** Sent to a ticket's assigned agents right after it's submitted. */
function send_new_ticket_notification(int $ticketId, string $ticketSubject): void
{
    $link = ticket_staff_link($ticketId);
    $subject = ticket_email_subject($ticketId, $ticketSubject);
    $body = "A new ticket was submitted and assigned to your group:\n{$ticketSubject}\n\n{$link}\n";
    notify_ticket_agents($ticketId, $subject, $body);
}

/**
 * Sent to a ticket's assigned agents when the submitter posts a new reply
 * via ticket-status.php.
 */
function send_ticket_reply_notification(int $ticketId, string $ticketSubject): void
{
    $link = ticket_staff_link($ticketId);
    $subject = ticket_email_subject($ticketId, $ticketSubject);
    $body = "The submitter added a new reply on ticket #{$ticketId}:\n{$ticketSubject}\n\n{$link}\n";
    notify_ticket_agents($ticketId, $subject, $body);
}

/**
 * Sent to the submitter when an agent updates a ticket (status/priority
 * change and/or a response). $response, when non-empty, is the agent's
 * reply text and is quoted directly in the email rather than making the
 * submitter click through just to read it. Throws on failure -- unlike the
 * other two notification functions, this one is triggered from an
 * authenticated agent action, so the agent should be told if it didn't go out.
 */
function send_ticket_update_notification(array $ticket, string $response = ''): void
{
    $link = ticket_public_link($ticket['public_token']);
    $subject = ticket_email_subject((int) $ticket['id'], $ticket['subject']);
    $body = "Hi {$ticket['requester_name']},\n\n" . "There's an update on your ticket.\n\n";
    if ($response !== '') {
        $body .= "Response:\n{$response}\n\n";
    }
    $body .= "View its current status and full conversation here:\n{$link}\n";
    send_ticket_email($ticket['requester_email'], $subject, $body, (int) $ticket['id']);
}
