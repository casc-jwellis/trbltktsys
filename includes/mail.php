<?php
// SMTP settings and mail sending, via the vendored tehsmtp library in
// lib/tehsmtp (no Composer -- same hand-vendoring approach as lib/tehimap,
// which includes/imap.php uses for inbound mail).
require_once __DIR__ . '/../lib/tehsmtp/tehsmtp.php';

use Tehsmtp\Address;
use Tehsmtp\Mailer;
use Tehsmtp\Message;
use Tehsmtp\PlaintextAuth;
use Tehsmtp\Tls;

const SMTP_ENCRYPTIONS = ['none', 'tls', 'ssl'];

/**
 * Light/dark badge colors for each ticket status, used in the HTML emails
 * built below. Kept as a flat color pair (not a CSS variable) because the
 * badge's dark-mode color has to live in an actual @media block per email --
 * see email_shell().
 */
const TICKET_STATUS_EMAIL_COLORS = [
    'Open'        => ['fg' => '#1d5fb0', 'bg' => '#dfeaf9', 'fgDark' => '#8fb8ee', 'bgDark' => '#1c2c42'],
    'In Progress' => ['fg' => '#93600a', 'bg' => '#f7e8c2', 'fgDark' => '#e3b95c', 'bgDark' => '#3a2f14'],
    'Resolved'    => ['fg' => '#1f7a4d', 'bg' => '#dcf0e4', 'fgDark' => '#6cd39c', 'bgDark' => '#163526'],
    'Closed'      => ['fg' => '#5b6472', 'bg' => '#e7e9ec', 'fgDark' => '#aeb4bd', 'bgDark' => '#252a31'],
];

/**
 * Light/dark pill colors for each ticket priority, shown on the agent
 * "new ticket" email. Unlike the status badge these are emitted as fixed
 * classes by email_shell(), since every email gets the same four rules.
 */
const TICKET_PRIORITY_EMAIL_COLORS = [
    'Low'    => ['fg' => '#4b5563', 'bg' => '#e7e9ed', 'fgDark' => '#b6bcc6', 'bgDark' => '#2a2f37'],
    'Medium' => ['fg' => '#1d5fb0', 'bg' => '#dfeaf9', 'fgDark' => '#8fb8ee', 'bgDark' => '#1c2c42'],
    'High'   => ['fg' => '#93600a', 'bg' => '#f7e8c2', 'fgDark' => '#e3b95c', 'bgDark' => '#3a2f14'],
    'Urgent' => ['fg' => '#b42318', 'bg' => '#fbdcdc', 'fgDark' => '#f19a94', 'bgDark' => '#40191a'],
];

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

/**
 * A tehsmtp Mailer for the given SMTP settings row. The Encryption setting
 * maps to a transport mode: "SSL/TLS" is implicit TLS, "STARTTLS" is
 * STARTTLS that must succeed (nothing is sent in the clear if the server
 * doesn't offer it), and "None" is plaintext. The server's certificate and
 * host name are always verified, and credentials are only ever sent over
 * TLS -- or to a loopback relay when Encryption is None. Throws
 * Tehsmtp\ConfigException for a combination it won't use, e.g. SSL/TLS on
 * port 587 or STARTTLS on port 465.
 */
function smtp_mailer(array $settings): Mailer
{
    $hasCredentials = $settings['username'] !== null && $settings['username'] !== '';

    return new Mailer(
        host: $settings['host'],
        port: (int) $settings['port'],
        tls: match ($settings['encryption']) {
            'ssl'   => Tls::Implicit,
            'tls'   => Tls::StartTls,
            default => Tls::None,
        },
        username: $hasCredentials ? $settings['username'] : null,
        password: $hasCredentials ? (string) $settings['password'] : null,
        plaintextAuth: PlaintextAuth::LoopbackOnly,
    );
}

/**
 * The email itself: HTML with a plain-text alternative for clients that
 * block or don't render HTML. tehsmtp encodes the (long, unbroken) HTML body
 * with proper line folding, so a several-KB single SMTP line can't slow down
 * or trip the receiving server's content scanners.
 *
 * $threadMessageId is the ticket's shared Message-ID (see
 * ticket_thread_message_id()), or null for an email outside any ticket
 * thread: the root email sends it as its own Message-ID ($isRoot), and every
 * later one points at it with In-Reply-To/References.
 */
function ticket_email_message(
    array $settings,
    string $toEmail,
    string $subject,
    string $htmlBody,
    string $textBody,
    ?string $threadMessageId = null,
    bool $isRoot = false
): Message {
    $message = (new Message())
        ->from(new Address($settings['from_email'], (string) ($settings['from_name'] ?? '')))
        ->to($toEmail)
        ->subject($subject)
        ->html($htmlBody)
        ->text($textBody);

    if ($threadMessageId !== null) {
        if ($isRoot) {
            $message->messageId($threadMessageId);
        } else {
            $message->inReplyTo($threadMessageId)->references($threadMessageId);
        }
    }

    return $message;
}

/**
 * Builds a mailer from the stored settings and sends one HTML email (with a
 * plain-text alternative). Throws with a human-readable reason on failure
 * (including when SMTP isn't configured yet) -- callers decide whether that
 * should block the surrounding action, surface a warning, or just be logged.
 * A Tehsmtp\DeliveryUnknownException means the server may still have
 * accepted the message, so it must never be sent again automatically.
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
function send_ticket_email(
    string $toEmail,
    string $subject,
    string $htmlBody,
    string $textBody,
    ?int $ticketId = null,
    bool $isRoot = false
): void {
    $settings = get_smtp_settings();
    if (!$settings || $settings['host'] === '' || !$settings['from_email']) {
        throw new RuntimeException('SMTP settings are not fully configured yet.');
    }

    $message = ticket_email_message(
        $settings,
        $toEmail,
        $subject,
        $htmlBody,
        $textBody,
        $ticketId !== null ? ticket_thread_message_id($ticketId) : null,
        $isRoot
    );

    $mailer = smtp_mailer($settings);
    try {
        $mailer->send($message);
    } finally {
        $mailer->close();
    }
}

/**
 * Sends a plain-text test message to $toEmail using the stored SMTP
 * settings. Throws with a human-readable reason on failure.
 */
function send_test_email(string $toEmail): void
{
    $appName = app_name();
    $message = "This is a test email from {$appName}, sent to confirm the SMTP settings in Admin Settings are working.";
    $html = email_shell(email_intro(null, $message));

    send_ticket_email($toEmail, 'Test email from ' . $appName, $html, $message);
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

// ---------------------------------------------------------------------------
// HTML email building blocks
//
// Every ticket email shares one visual shell (email_shell): a white
// card on a light canvas, with an accent header bar and the app name as an
// eyebrow label. Colors are set via CSS classes rather than inline styles so
// the @media (prefers-color-scheme: dark) block can repaint them for
// recipients whose mail client is in dark mode. The tradeoff: mail clients
// that ignore <style> blocks entirely (notably classic Outlook desktop) fall
// back to plain black-on-white -- layout and the button still work there,
// just without color.
//
// Each send_ticket_*() function below builds an HTML body from these pieces
// and a parallel plain-text body from ticket_email_plain() -- the message
// carries the HTML and the plain text as alternatives, so clients that
// block or can't render HTML still get a readable message.
// ---------------------------------------------------------------------------

/** Wraps inner content (a sequence of <tr> rows) in the shared card shell. */
function email_shell(string $innerHtml, ?array $statusColors = null): string
{
    $badgeCss = '';
    if ($statusColors !== null) {
        $badgeCss = '.badge{background:' . $statusColors['bg'] . ';color:' . $statusColors['fg'] . ';}' . "\n"
            . '@media (prefers-color-scheme: dark){.badge{background:' . $statusColors['bgDark'] . ';color:' . $statusColors['fgDark'] . ';}}' . "\n";
    }

    $eyebrow = e(app_name());

    $priorityCss = '';
    $priorityDarkCss = '';
    foreach (TICKET_PRIORITY_EMAIL_COLORS as $priorityName => $c) {
        $class = '.pill-' . strtolower($priorityName);
        $priorityCss .= $class . '{background:' . $c['bg'] . ';color:' . $c['fg'] . ';}' . "\n";
        $priorityDarkCss .= $class . '{background:' . $c['bgDark'] . ';color:' . $c['fgDark'] . ';}' . "\n";
    }

    $styleLines = [
        'body{margin:0;font-family:-apple-system,"Segoe UI",Roboto,Arial,sans-serif;}',
        '.canvas{background:#eef1f6;}',
        '.card{background:#ffffff;border:1px solid #dde2e9;}',
        '.eyebrow{color:#8891a0;}',
        '.heading{color:#1c2128;}',
        '.sub{color:#3c4450;}',
        '.meta-box{background:#f6f7f9;border:1px solid #e5e8ec;}',
        '.meta-label{color:#8891a0;}',
        '.meta-subject{color:#1c2128;}',
        '.meta-priority{color:#8891a0;}',
        '.callout-label{color:#8891a0;}',
        '.callout-text{color:#2a2f38;}',
        '.note-text{color:#6b7280;}',
        '.link-fallback{color:#98a1ad;}',
        '.footer-text{color:#98a1ad;}',
        '.footer-cell{border-top:1px solid #eceef1;}',
        '.tk-card{border:1px solid #dde2e9;}',
        '.tk-head{color:#1c2128;border-bottom:1px solid #e5e8ec;}',
        '.tk-label{color:#6b7280;}',
        '.pill-new{background:#dfeaf9;color:#1d5fb0;}',
        '.pill-agent{background:#dcf0e4;color:#1f7a4d;}',
        $priorityCss,
        $badgeCss,
        '@media (prefers-color-scheme: dark){',
        'body,.canvas{background:#12151a;}',
        '.card{background:#1a1e25;border-color:#2b3038;}',
        '.eyebrow{color:#8890a0;}',
        '.heading{color:#f0f2f4;}',
        '.sub{color:#c3c9d1;}',
        '.meta-box{background:#20242c;border-color:#2b3038;}',
        '.meta-label{color:#8890a0;}',
        '.meta-subject{color:#f0f2f4;}',
        '.meta-priority{color:#8890a0;}',
        '.callout-label{color:#8890a0;}',
        '.callout-text{color:#dfe3e8;}',
        '.note-text{color:#9aa3b0;}',
        '.link-fallback{color:#7d848f;}',
        '.footer-text{color:#7d848f;}',
        '.footer-cell{border-color:#2b3038;}',
        '.tk-card{border-color:#2b3038;}',
        '.tk-head{color:#f0f2f4;border-color:#2b3038;}',
        '.tk-label{color:#9aa3b0;}',
        '.pill-new{background:#1c2c42;color:#8fb8ee;}',
        '.pill-agent{background:#163526;color:#6cd39c;}',
        $priorityDarkCss,
        '}',
    ];
    $style = implode("\n", $styleLines);

    $docLines = [
        '<!doctype html>',
        '<html>',
        '<head>',
        '<meta charset="utf-8">',
        '<meta name="color-scheme" content="light dark">',
        '<meta name="supported-color-schemes" content="light dark">',
        '<style>',
        $style,
        '</style>',
        '</head>',
        '<body>',
        '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="canvas"><tr><td align="center" style="padding:28px 14px;">',
        '<table role="presentation" width="600" cellpadding="0" cellspacing="0" class="card" style="max-width:600px;width:100%;border-radius:12px;">',
        '<tr><td style="background:#2d4f8f;height:6px;line-height:6px;font-size:0;border-radius:12px 12px 0 0;">&nbsp;</td></tr>',
        '<tr><td style="padding:24px 32px 0;"><div class="eyebrow" style="font-family:Arial,sans-serif;font-size:12px;letter-spacing:.08em;text-transform:uppercase;">' . $eyebrow . '</div></td></tr>',
        $innerHtml,
        '</table>',
        '</td></tr></table>',
        '</body>',
        '</html>',
    ];

    return implode("\n", $docLines);
}

/** Greeting row. $name null omits the "Hi {name}," line (used for agent emails, which aren't addressed to one person). */
function email_intro(?string $name, string $sentence): string
{
    $greeting = $name !== null
        ? '<p class="heading" style="margin:0;font-size:21px;font-weight:600;">Hi ' . e($name) . ',</p>' . "\n"
        : '';
    $topPad = $name !== null ? '16' : '22';
    $subMargin = $name !== null ? '8px 0 0' : '0';

    return '<tr><td style="padding:' . $topPad . 'px 32px 0;">' . "\n"
        . $greeting
        . '<p class="sub" style="margin:' . $subMargin . ';font-size:15px;line-height:1.5;">' . e($sentence) . '</p>' . "\n"
        . '</td></tr>';
}

/** The ticket-number / subject / status-badge box. $priority, when given, is shown next to the badge (agent emails only -- submitters aren't shown priority). */
function ticket_email_meta_box(int $ticketId, string $subject, string $status, array $statusColors, ?string $priority = null): string
{
    $priorityHtml = $priority !== null
        ? '<span class="meta-priority" style="display:inline-block;margin-left:8px;font-size:12px;font-family:Arial,sans-serif;">Priority: ' . e($priority) . '</span>'
        : '';

    return '<tr><td style="padding:18px 32px 0;">' . "\n"
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="meta-box" style="border-radius:8px;"><tr><td style="padding:16px 20px;">' . "\n"
        . '<div class="meta-label" style="font-size:12px;font-family:Arial,sans-serif;">Ticket #' . $ticketId . '</div>' . "\n"
        . '<div class="meta-subject" style="font-size:16px;font-weight:600;margin-top:3px;">' . e($subject) . '</div>' . "\n"
        . '<div style="margin-top:12px;">' . "\n"
        . '<span class="badge" style="display:inline-block;font-size:12px;font-weight:700;letter-spacing:.03em;padding:4px 11px;border-radius:999px;font-family:Arial,sans-serif;">' . e(mb_strtoupper($status)) . '</span>' . "\n"
        . $priorityHtml . "\n"
        . '</div>' . "\n"
        . '</td></tr></table>' . "\n"
        . '</td></tr>';
}

/**
 * The agent "new ticket" card, laid out like the Ticket card at the top of the
 * ticket page: the ticket number and priority in a header row, then the
 * subject, who submitted it and when, and the description. The page's
 * dropdowns and buttons are static text here. $createdBy is the agent's name
 * when they opened the ticket on a requester's behalf.
 */
function ticket_email_card(
    int $ticketId,
    string $subject,
    string $priority,
    string $requesterName,
    string $createdAt,
    string $description,
    ?string $createdBy = null
): string {
    $pill = 'display:inline-block;font-size:11px;font-weight:700;letter-spacing:.03em;padding:4px 10px;border-radius:999px;font-family:Arial,sans-serif;text-transform:uppercase;vertical-align:middle;';
    $byline = ($createdBy !== null ? 'Requested by ' : 'Submitted by ') . $requesterName . ' on ' . format_db_time($createdAt);
    $agentBadge = $createdBy !== null
        ? '<div style="margin-top:8px;"><span class="pill-agent" style="display:inline-block;font-size:12px;font-weight:600;padding:3px 10px;border-radius:999px;font-family:Arial,sans-serif;">Created by agent: ' . e($createdBy) . '</span></div>'
        : '';

    return '<tr><td style="padding:18px 32px 0;">' . "\n"
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="tk-card" style="border-radius:12px;">' . "\n"
        . '<tr><td class="tk-head" style="padding:12px 20px;font-size:15px;font-weight:600;">' . "\n"
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>' . "\n"
        . '<td style="font-size:15px;font-weight:600;">Ticket #' . $ticketId . ' <span class="pill-new" style="' . $pill . 'margin-left:6px;">New</span></td>' . "\n"
        . '<td align="right" style="white-space:nowrap;"><span class="tk-label" style="font-size:12px;font-family:Arial,sans-serif;margin-right:6px;">Priority</span>'
        . '<span class="pill-' . e(strtolower($priority)) . '" style="' . $pill . '">' . e($priority) . '</span></td>' . "\n"
        . '</tr></table>' . "\n"
        . '</td></tr>' . "\n"
        . '<tr><td style="padding:20px;">' . "\n"
        . '<h1 class="meta-subject" style="margin:0;font-size:20px;line-height:1.3;font-weight:600;">' . e($subject) . '</h1>' . "\n"
        . '<p class="tk-label" style="margin:8px 0 0;font-size:14px;line-height:1.5;">' . e($byline) . '</p>' . "\n"
        . $agentBadge . "\n"
        . '<p class="callout-text" style="margin:16px 0 0;font-size:15px;line-height:1.55;white-space:pre-wrap;">' . e($description) . '</p>' . "\n"
        . '</td></tr>' . "\n"
        . '</table>' . "\n"
        . '</td></tr>';
}

/** An accent-bordered quote block -- used for an agent's response and for a submitter's reply, quoted directly so the reader doesn't have to click through. */
function ticket_email_callout(string $label, string $text): string
{
    return '<tr><td style="padding:20px 32px 0;">' . "\n"
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>' . "\n"
        . '<td width="3" style="background:#2d4f8f;font-size:0;">&nbsp;</td>' . "\n"
        . '<td style="padding:2px 0 2px 16px;">' . "\n"
        . '<div class="callout-label" style="font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;font-family:Arial,sans-serif;">' . e($label) . '</div>' . "\n"
        . '<p class="callout-text" style="margin:6px 0 0;font-size:15px;line-height:1.55;white-space:pre-wrap;">' . e($text) . '</p>' . "\n"
        . '</td></tr></table>' . "\n"
        . '</td></tr>';
}

/** A small italic note, e.g. the "reply to reopen" hint on a Resolved/Closed update. */
function email_note(string $text): string
{
    return '<tr><td style="padding:16px 32px 0;">' . "\n"
        . '<p class="note-text" style="margin:0;font-size:13px;font-style:italic;">' . e($text) . '</p>' . "\n"
        . '</td></tr>';
}

/** The call-to-action button, plus its URL repeated as plain text underneath for clients that strip links or images. */
function email_button(string $url, string $label): string
{
    return '<tr><td style="padding:24px 32px 6px;"><table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="background:#2d4f8f;border-radius:6px;">' . "\n"
        . '<a href="' . e($url) . '" style="display:inline-block;padding:12px 26px;font-family:Arial,sans-serif;font-size:14px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:6px;">' . e($label) . '</a>' . "\n"
        . '</td></tr></table></td></tr>' . "\n"
        . '<tr><td style="padding:2px 32px 24px;"><p class="link-fallback" style="margin:0;font-size:12px;word-break:break-all;font-family:Arial,sans-serif;">' . e($url) . '</p></td></tr>';
}

/** The username/temporary-password box on a new-account email. Same visual language as ticket_email_meta_box(), just for credentials instead of ticket details. */
function account_credentials_box(string $username, string $password): string
{
    return '<tr><td style="padding:18px 32px 0;">' . "\n"
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="meta-box" style="border-radius:8px;"><tr><td style="padding:16px 20px;">' . "\n"
        . '<div class="meta-label" style="font-size:12px;font-family:Arial,sans-serif;">Username</div>' . "\n"
        . '<div class="meta-subject" style="font-size:16px;font-weight:600;font-family:\'Courier New\',monospace;margin-top:3px;">' . e($username) . '</div>' . "\n"
        . '<div class="meta-label" style="font-size:12px;font-family:Arial,sans-serif;margin-top:14px;">Temporary password</div>' . "\n"
        . '<div class="meta-subject" style="font-size:16px;font-weight:600;font-family:\'Courier New\',monospace;margin-top:3px;">' . e($password) . '</div>' . "\n"
        . '</td></tr></table>' . "\n"
        . '</td></tr>';
}

/**
 * Whether inbound email replies are being pulled in (Admin Settings, Email
 * tab) -- the same enabled-and-configured test the IMAP poller applies. Read
 * straight from imap_settings so the web pages that send mail don't have to
 * load the IMAP client (includes/imap.php).
 */
function inbound_email_replies_enabled(): bool
{
    static $result = null;
    if ($result === null) {
        $result = false;
        try {
            if (table_exists('imap_settings')) {
                $row = db()->query('SELECT enabled, host, username FROM imap_settings WHERE id = 1')->fetch();
                $result = $row && $row['enabled'] && $row['host'] !== '' && !empty($row['username']);
            }
        } catch (Throwable $e) {
            error_log('Could not read IMAP settings for an email footer: ' . $e->getMessage());
        }
    }
    return $result;
}

/**
 * What the footer says about replying to the email itself. A reply is only
 * turned into a ticket message when it comes from the ticket's submitter (see
 * verify_ticket_reply_target()), so that's the only audience told they can
 * reply -- and only while inbound mail is on. Agents are always pointed at the
 * link instead.
 */
function ticket_email_reply_hint(bool $forAgent): string
{
    if ($forAgent) {
        return 'Replying to this email will not update the ticket. Use the link above instead.';
    }
    if (inbound_email_replies_enabled()) {
        return 'You can reply to this email to add a message to your ticket, or use the link above.';
    }
    return "This inbox is not monitored \u{2014} replying to this email will not reach anyone. Use the link above instead.";
}

/** Footer shown on every ticket email: which ticket it's about, and whether replying to it does anything. */
function ticket_email_footer(int $ticketId, bool $forAgent = false): string
{
    return '<tr><td class="footer-cell" style="padding:16px 32px 22px;">' . "\n"
        . '<p class="footer-text" style="margin:0;font-size:12px;font-family:Arial,sans-serif;line-height:1.5;">' . "\n"
        . 'This message is about ticket #' . $ticketId . '. ' . e(ticket_email_reply_hint($forAgent)) . "\n"
        . '</p></td></tr>';
}

/**
 * Plain-text counterpart to the HTML pieces above, built from the same
 * arguments so the text alternative can't drift out of sync with the HTML body.
 * $callout is [label, text] or null; $note is the reopen hint or null.
 */
function ticket_email_plain(
    ?string $name,
    string $sentence,
    int $ticketId,
    string $subject,
    string $status,
    ?string $priority,
    ?array $callout,
    ?string $note,
    string $url,
    bool $forAgent = false
): string {
    $rule = str_repeat('-', 58);
    $lines = [];

    if ($name !== null) {
        $lines[] = "Hi {$name},";
        $lines[] = '';
    }
    $lines[] = $sentence;
    $lines[] = '';
    $lines[] = $rule;
    $lines[] = "Ticket #{$ticketId}: {$subject}";
    $lines[] = 'Status: ' . $status . ($priority !== null ? " | Priority: {$priority}" : '');
    $lines[] = $rule;
    $lines[] = '';

    if ($callout !== null) {
        [$label, $text] = $callout;
        $lines[] = "{$label}:";
        $lines[] = $text;
        $lines[] = '';
    }

    if ($note !== null) {
        $lines[] = $note;
        $lines[] = '';
    }

    $lines[] = 'View the full conversation or add a reply:';
    $lines[] = $url;
    $lines[] = '';
    $lines[] = str_replace("\u{2014}", '--', ticket_email_reply_hint($forAgent));

    return implode("\n", $lines);
}

// ---------------------------------------------------------------------------
// The four ticket emails
// ---------------------------------------------------------------------------

/**
 * Sent once, right after a ticket is submitted. This is the root of the
 * ticket's email thread -- see send_ticket_email().
 */
function send_ticket_confirmation_email(array $ticket): void
{
    $ticketId = (int) $ticket['id'];
    $link = ticket_public_link($ticket['public_token']);
    $subject = ticket_email_subject($ticketId, $ticket['subject']);
    // An agent can open a ticket (create-ticket.php) at any status and name
    // themselves in $ticket['created_by_name']; a self-submitted ticket is
    // always a plain, new "Open" one.
    $status = $ticket['status'] ?? 'Open';
    $colors = TICKET_STATUS_EMAIL_COLORS[$status] ?? TICKET_STATUS_EMAIL_COLORS['Open'];
    $sentence = !empty($ticket['created_by_name'])
        ? "{$ticket['created_by_name']} from the helpdesk opened this ticket on your behalf. We'll get back to you soon."
        : "We've received your ticket and will get back to you soon.";

    $html = email_shell(
        email_intro($ticket['requester_name'], $sentence) . "\n"
        . ticket_email_meta_box($ticketId, $ticket['subject'], $status, $colors) . "\n"
        . email_button($link, 'View ticket status') . "\n"
        . ticket_email_footer($ticketId),
        $colors
    );

    $text = ticket_email_plain($ticket['requester_name'], $sentence, $ticketId, $ticket['subject'], $status, null, null, null, $link);

    send_ticket_email($ticket['requester_email'], $subject, $html, $text, $ticketId, true);
}

/**
 * Emails every active agent in a ticket's assigned groups. Best-effort: a
 * failure for one recipient is logged and doesn't stop the others, and the
 * caller never sees an exception here -- these are background notifications,
 * not something the (anonymous, unauthenticated) submitter should see fail.
 */
function notify_ticket_agents(int $ticketId, string $subject, string $htmlBody, string $textBody, ?string $excludeEmail = null): void
{
    foreach (ticket_assigned_agent_emails($ticketId) as $recipient) {
        if ($excludeEmail !== null && strcasecmp($recipient['email'], $excludeEmail) === 0) {
            continue;
        }
        try {
            send_ticket_email($recipient['email'], $subject, $htmlBody, $textBody, $ticketId);
        } catch (Throwable $e) {
            error_log("Failed to notify {$recipient['email']} about ticket #{$ticketId}: " . $e->getMessage());
        }
    }
}

/**
 * Sent to a ticket's assigned agents right after it's submitted. When an
 * agent opened it on a requester's behalf, $createdBy names them and
 * $excludeEmail (their own address) keeps them from being emailed about
 * their own ticket.
 */
function send_new_ticket_notification(
    int $ticketId,
    string $ticketSubject,
    string $priority,
    ?string $createdBy = null,
    ?string $excludeEmail = null
): void {
    $link = ticket_staff_link($ticketId);
    $subject = ticket_email_subject($ticketId, $ticketSubject);
    $sentence = $createdBy !== null
        ? "{$createdBy} opened a new ticket on a requester's behalf. It is assigned to you or your group."
        : 'A new ticket was submitted and assigned to your group.';

    // The email shows what the ticket page shows (who, when, and what was
    // asked), so read those from the ticket itself -- both callers have just
    // committed it.
    $stmt = db()->prepare('SELECT requester_name, description, created_at FROM tickets WHERE id = ?');
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        return;
    }

    $html = email_shell(
        email_intro(null, $sentence) . "\n"
        . ticket_email_card($ticketId, $ticketSubject, $priority, $ticket['requester_name'], $ticket['created_at'], $ticket['description'], $createdBy) . "\n"
        . email_button($link, 'View ticket') . "\n"
        . ticket_email_footer($ticketId, true)
    );

    $rule = str_repeat('-', 58);
    $text = implode("\n", [
        $sentence,
        '',
        $rule,
        "Ticket #{$ticketId} (New) | Priority: {$priority}",
        $ticketSubject,
        ($createdBy !== null ? 'Requested by ' : 'Submitted by ') . $ticket['requester_name'] . ' on ' . format_db_time($ticket['created_at'])
            . ($createdBy !== null ? " (created by agent: {$createdBy})" : ''),
        $rule,
        '',
        $ticket['description'],
        '',
        'View the ticket:',
        $link,
        '',
        ticket_email_reply_hint(true),
    ]);

    notify_ticket_agents($ticketId, $subject, $html, $text, $excludeEmail);
}

/**
 * Sent to a ticket's assigned agents when the submitter posts a new reply
 * via ticket-status.php. $replyBody is quoted directly so agents don't have
 * to click through just to read it.
 */
function send_ticket_reply_notification(int $ticketId, string $ticketSubject, string $status, string $replyBody): void
{
    $link = ticket_staff_link($ticketId);
    $subject = ticket_email_subject($ticketId, $ticketSubject);
    $colors = TICKET_STATUS_EMAIL_COLORS[$status] ?? TICKET_STATUS_EMAIL_COLORS['Open'];
    $sentence = 'The submitter added a new reply on this ticket.';

    $html = email_shell(
        email_intro(null, $sentence) . "\n"
        . ticket_email_meta_box($ticketId, $ticketSubject, $status, $colors) . "\n"
        . ticket_email_callout('Message from the submitter', $replyBody) . "\n"
        . email_button($link, 'View ticket & reply') . "\n"
        . ticket_email_footer($ticketId, true),
        $colors
    );

    $text = ticket_email_plain(null, $sentence, $ticketId, $ticketSubject, $status, null, ['Message from the submitter', $replyBody], null, $link, true);

    notify_ticket_agents($ticketId, $subject, $html, $text);
}

/**
 * Sent to the submitter when an agent updates a ticket (status/priority
 * change and/or a response). $response, when non-empty, is the agent's
 * reply text and is quoted directly in the email rather than making the
 * submitter click through just to read it. Throws on failure -- unlike the
 * other two notification functions, this one is triggered from an
 * authenticated agent action, so the agent should be told if it didn't go
 * out. $ticket['status'] must already reflect the value the agent just
 * saved, not a value fetched before the update.
 */
function send_ticket_update_notification(array $ticket, string $response = '', array $attachmentNames = [], ?string $agentName = null): void
{
    $ticketId = (int) $ticket['id'];
    $link = ticket_public_link($ticket['public_token']);
    $subject = ticket_email_subject($ticketId, $ticket['subject']);
    $status = $ticket['status'];
    $colors = TICKET_STATUS_EMAIL_COLORS[$status] ?? TICKET_STATUS_EMAIL_COLORS['Open'];
    $hasResponse = $response !== '';
    // $agentName is the agent who made the change, so the requester knows
    // who they're hearing from; "support" is the generic fallback.
    $sentence = $hasResponse
        ? ($agentName !== null ? "{$agentName} updated your ticket." : 'Your ticket has been updated.')
        : ($agentName !== null ? "{$agentName} changed the status of your ticket." : 'Your ticket status has changed.');
    $responseLabel = 'Response from ' . ($agentName ?? 'support');
    $showReopenNote = in_array($status, ['Resolved', 'Closed'], true);
    $reopenNoteText = "Didn't fully fix it? Use the link below to reply and we'll reopen this ticket.";
    // Files aren't attached to the email itself -- they're served from the
    // ticket page, so name them and point there.
    $attachmentNoteText = $attachmentNames
        ? 'Attached to this response: ' . implode(', ', $attachmentNames) . '. Open the ticket below to download '
            . (count($attachmentNames) === 1 ? 'it.' : 'them.')
        : null;

    $inner = email_intro($ticket['requester_name'], $sentence) . "\n"
        . ticket_email_meta_box($ticketId, $ticket['subject'], $status, $colors);
    if ($hasResponse) {
        $inner .= "\n" . ticket_email_callout($responseLabel, $response);
    }
    if ($attachmentNoteText !== null) {
        $inner .= "\n" . email_note($attachmentNoteText);
    }
    if ($showReopenNote) {
        $inner .= "\n" . email_note($reopenNoteText);
    }
    $inner .= "\n" . email_button($link, 'View ticket & reply') . "\n" . ticket_email_footer($ticketId);

    $html = email_shell($inner, $colors);

    $text = ticket_email_plain(
        $ticket['requester_name'],
        $sentence,
        $ticketId,
        $ticket['subject'],
        $status,
        null,
        $hasResponse ? [$responseLabel, $response] : null,
        implode("\n", array_filter([$attachmentNoteText, $showReopenNote ? $reopenNoteText : null])) ?: null,
        $link
    );

    send_ticket_email($ticket['requester_email'], $subject, $html, $text, $ticketId);
}

// ---------------------------------------------------------------------------
// New-account email
// ---------------------------------------------------------------------------

/**
 * Sent to a new agent/admin account right after an admin creates it in Admin
 * Settings. There's no separate first-login/activation flow -- the account
 * has to be usable immediately, so the password the admin set is included
 * directly, and the recipient is pushed hard to change it themselves.
 */
function send_new_user_email(string $email, string $fullName, string $username, string $password): void
{
    $appName = app_name();
    $loginUrl = base_url() . '/login.php';
    $subject = "Your {$appName} account";
    $sentence = "An account has been created for you on {$appName}.";
    $warning = 'For your security, please log in and change this password as soon as possible.';

    $html = email_shell(
        email_intro($fullName, $sentence) . "\n"
        . account_credentials_box($username, $password) . "\n"
        . email_note($warning) . "\n"
        . email_button($loginUrl, 'Log in')
    );

    $lines = [
        "Hi {$fullName},",
        '',
        $sentence,
        '',
        'Username: ' . $username,
        'Temporary password: ' . $password,
        '',
        $warning,
        '',
        'Log in here:',
        $loginUrl,
    ];
    $text = implode("\n", $lines);

    send_ticket_email($email, $subject, $html, $text);
}
