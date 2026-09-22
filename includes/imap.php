<?php
// Inbound mail: settings and the actual "pull submitter replies in over
// IMAP" pipeline, via the vendored raw-socket IMAP4rev1 client in
// lib/tehimap (no Composer -- same hand-vendoring approach as PHPMailer in
// lib/phpmailer). Run from the CLI via bin/imap-poll.php, on whatever
// schedule the server's task runner is configured for -- there's no
// built-in cron here.
require_once __DIR__ . '/../lib/tehimap/autoload.php';

use Tehimap\Imap\Auth\PlainAuthenticator;
use Tehimap\Imap\Client;
use Tehimap\Imap\Exception\ImapException;
use Tehimap\Imap\Message\MessageHeaders;
use Tehimap\Imap\Message\MessagePart;
use Tehimap\Imap\Search\SearchQuery;
use Tehimap\Imap\Sync\FileSyncState;
use Tehimap\Imap\Sync\SyncCursor;

// STARTTLS isn't wired up end-to-end yet (the connection layer supports it,
// but nothing here negotiates it) -- only implicit TLS and plaintext are
// offered.
const IMAP_ENCRYPTIONS = ['ssl', 'none'];

const IMAP_ATTACHMENT_MAX_BYTES = 15 * 1024 * 1024;
const IMAP_ATTACHMENT_MIME_EXTENSIONS = [
    'image/png'  => 'png',
    'image/jpeg' => 'jpg',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    'application/pdf' => 'pdf',
    'text/plain' => 'txt',
    'text/csv'   => 'csv',
    'application/zip' => 'zip',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.ms-excel' => 'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    'application/vnd.ms-powerpoint' => 'ppt',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
];

// ---------------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------------

/** Whether the imap_settings table exists yet (migration 018). */
function imap_settings_supported(): bool
{
    static $result = null;
    if ($result === null) {
        $result = table_exists('imap_settings');
    }
    return $result;
}

/**
 * The single IMAP settings row, or defaults if the table exists but the
 * seed row is somehow missing. Null only when the table itself doesn't
 * exist yet (pending migration).
 */
function get_imap_settings(): ?array
{
    if (!imap_settings_supported()) {
        return null;
    }
    $row = db()->query('SELECT * FROM imap_settings WHERE id = 1')->fetch();
    return $row ?: [
        'enabled' => 0, 'host' => '', 'port' => 993, 'encryption' => 'ssl',
        'username' => '', 'password' => null,
        'mailbox' => 'INBOX', 'processed_mailbox' => 'Processed', 'unmatched_mailbox' => 'Unmatched',
    ];
}

/**
 * Updates the single IMAP settings row. $password may be null to leave the
 * currently stored password unchanged (mirrors save_smtp_settings()).
 */
function save_imap_settings(
    bool $enabled,
    string $host,
    int $port,
    string $encryption,
    string $username,
    ?string $password,
    string $mailbox,
    string $processedMailbox,
    string $unmatchedMailbox
): void {
    if ($password !== null) {
        $stmt = db()->prepare(
            'UPDATE imap_settings SET enabled = ?, host = ?, port = ?, encryption = ?, username = ?, password = ?,
                mailbox = ?, processed_mailbox = ?, unmatched_mailbox = ? WHERE id = 1'
        );
        $stmt->execute([$enabled ? 1 : 0, $host, $port, $encryption, $username, $password, $mailbox, $processedMailbox, $unmatchedMailbox]);
    } else {
        $stmt = db()->prepare(
            'UPDATE imap_settings SET enabled = ?, host = ?, port = ?, encryption = ?, username = ?,
                mailbox = ?, processed_mailbox = ?, unmatched_mailbox = ? WHERE id = 1'
        );
        $stmt->execute([$enabled ? 1 : 0, $host, $port, $encryption, $username, $mailbox, $processedMailbox, $unmatchedMailbox]);
    }
}

/** Builds, connects, and logs in a Client from the stored IMAP settings. */
function configured_imap_client(): Client
{
    $settings = get_imap_settings();
    if (!$settings || $settings['host'] === '' || !$settings['username']) {
        throw new ImapException('IMAP settings are not fully configured yet.');
    }

    $client = new Client(
        $settings['host'],
        (int) $settings['port'],
        $settings['encryption'] === 'ssl',
        new PlainAuthenticator($settings['username'], (string) $settings['password'])
    );
    $client->connect();
    $client->login();

    return $client;
}

/**
 * Connects, checks the configured mailbox exists, and logs back out --
 * confirms the settings actually work without processing anything. Throws
 * with a human-readable reason on failure.
 */
function test_imap_connection(): array
{
    $settings = get_imap_settings();
    if (!$settings) {
        throw new ImapException('IMAP settings are not available yet.');
    }

    $client = configured_imap_client();
    try {
        return $client->status($settings['mailbox'], ['MESSAGES', 'UNSEEN']);
    } finally {
        $client->logout();
    }
}

// ---------------------------------------------------------------------------
// Inbound processing
// ---------------------------------------------------------------------------

/**
 * Pulls in new messages since the last run and posts each one that's a
 * verified reply to a ticket as a submitter comment (see
 * verify_ticket_reply_target()). Returns a summary count; never throws for
 * a single bad message -- only a connection/config failure propagates, since
 * that means nothing here could be processed at all.
 *
 * Resumable via a UID cursor (see lib/tehimap/src/Sync) rather than relying
 * on \Seen state, so it's unaffected by someone else reading the mailbox
 * with a real mail client. On the very first run (no cursor yet) it works
 * through whatever's currently unseen instead of the whole mailbox history.
 */
function process_inbound_mail(): array
{
    $summary = ['processed' => 0, 'unmatched' => 0, 'empty' => 0, 'errors' => 0, 'skipped' => false];

    $settings = get_imap_settings();
    if (!$settings || !$settings['enabled']) {
        $summary['skipped'] = true;
        return $summary;
    }

    $client = configured_imap_client();

    try {
        $mailbox = $settings['mailbox'];
        $info = $client->selectMailbox($mailbox);

        $syncState = new FileSyncState(__DIR__ . '/../storage/imap-sync');
        $account = $settings['host'] . ':' . $settings['username'];
        $cursor = $syncState->load($account, $mailbox);

        if ($cursor !== null && $cursor->uidValidity === $info->uidValidity) {
            $uids = $client->search((new SearchQuery())->uid(($cursor->lastUid + 1) . ':*'));
        } else {
            // No cursor yet, or the mailbox was rebuilt server-side
            // (UIDVALIDITY changed) -- work through whatever's currently
            // unseen rather than either reprocessing everything or (worse)
            // silently skipping a backlog of genuine replies.
            $uids = $client->search((new SearchQuery())->unseen());
        }

        $highestUid = $cursor->lastUid ?? 0;

        foreach ($uids as $uid) {
            try {
                $outcome = process_inbound_message($client, $settings, $uid);
                $summary[$outcome]++;
            } catch (Throwable $e) {
                $summary['errors']++;
                error_log('IMAP: failed to process message UID ' . $uid . ': ' . $e->getMessage());
            }
            $highestUid = max($highestUid, $uid);
        }

        if ($uids) {
            $syncState->save($account, $mailbox, new SyncCursor($info->uidValidity, $highestUid));
        } elseif ($cursor === null) {
            // Nothing to do yet, but still record a starting point so a
            // quiet mailbox doesn't re-run the UNSEEN scan forever.
            $syncState->save($account, $mailbox, new SyncCursor($info->uidValidity, $info->uidNext - 1));
        }
    } finally {
        $client->logout();
    }

    return $summary;
}

/** Processes one message; returns 'processed', 'unmatched', or 'empty'. */
function process_inbound_message(Client $client, array $settings, int $uid): string
{
    $headerMessages = $client->fetchHeaders([$uid]);
    $headers = ($headerMessages[0] ?? null)?->getHeaders();

    $target = $headers !== null ? find_ticket_reply_target($headers) : null;
    $fromAddress = $target !== null ? extract_email_address($headers->get('From') ?? '') : '';
    $ticket = $target !== null ? verify_ticket_reply_target($target, $fromAddress) : null;

    if ($ticket === null) {
        mark_inbound_message_handled($client, $settings['unmatched_mailbox'], $uid);
        return 'unmatched';
    }

    $structureMessages = $client->fetchStructure([$uid]);
    $structure = ($structureMessages[0] ?? null)?->getStructure();
    [$text, $attachments] = extract_message_content($client, $uid, $structure);

    $body = strip_quoted_reply(trim($text));
    if ($body === '' && $attachments === []) {
        // A genuine reply to this ticket, but with nothing worth posting
        // (e.g. an empty "sent from my iPhone" reply) -- still mark it
        // handled (into Processed, since it was a verified match) so it
        // isn't retried forever.
        mark_inbound_message_handled($client, $settings['processed_mailbox'], $uid);
        return 'empty';
    }
    if ($body === '') {
        $body = '(No message text -- see attached file' . (count($attachments) === 1 ? '' : 's') . '.)';
    }

    add_ticket_comment((int) $ticket['id'], null, $body, false);
    $commentId = (int) db()->lastInsertId();

    if (ticket_comment_attachments_supported()) {
        $stmt = db()->prepare(
            'INSERT INTO ticket_comment_attachments (comment_id, path, original_filename, mime_type, size) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($attachments as $attachment) {
            $stmt->execute([$commentId, $attachment['path'], $attachment['filename'], $attachment['mimeType'], $attachment['size']]);
        }
    }

    // A submitter replying on a Resolved/Closed ticket means it isn't
    // actually settled -- reopen it, same as the web reply form (see
    // ticket-status.php).
    if (in_array($ticket['status'], ['Resolved', 'Closed'], true)) {
        db()->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute(['Open', $ticket['id']]);
        $ticket['status'] = 'Open';
    }

    try {
        send_ticket_reply_notification((int) $ticket['id'], $ticket['subject'], $ticket['status'], $body);
    } catch (Throwable $e) {
        error_log('IMAP: reply notification failed for ticket #' . $ticket['id'] . ': ' . $e->getMessage());
    }

    mark_inbound_message_handled($client, $settings['processed_mailbox'], $uid);
    return 'processed';
}

/**
 * Extracts the ticket ID (and, when present, the reply-verification
 * fragment) that a message's In-Reply-To/References headers point at -- see
 * ticket_thread_message_id(). Checks In-Reply-To first, then each entry in
 * References (a reply chain several messages deep may not repeat the direct
 * parent in In-Reply-To on every client).
 *
 * @return array{ticketId: int, fragment: ?string}|null
 */
function find_ticket_reply_target(MessageHeaders $headers): ?array
{
    $candidates = [];
    $inReplyTo = $headers->get('In-Reply-To');
    if ($inReplyTo !== null) {
        $candidates[] = $inReplyTo;
    }
    foreach (preg_split('/\s+/', trim($headers->get('References') ?? '')) as $reference) {
        if ($reference !== '') {
            $candidates[] = $reference;
        }
    }

    foreach ($candidates as $candidate) {
        if (preg_match('/<ticket-(\d+)-([0-9a-f]{20})@[^>]*>/i', $candidate, $m) === 1) {
            return ['ticketId' => (int) $m[1], 'fragment' => strtolower($m[2])];
        }
    }
    // Older threads (created before migration 014 added public tokens) have
    // no fragment to check -- fall back to the plain ticket-ID anchor, and
    // rely solely on the sender-address check in verify_ticket_reply_target().
    foreach ($candidates as $candidate) {
        if (preg_match('/<ticket-(\d+)@[^>]*>/i', $candidate, $m) === 1) {
            return ['ticketId' => (int) $m[1], 'fragment' => null];
        }
    }

    return null;
}

/**
 * Confirms a candidate reply target is genuine: the fragment (when present)
 * must match a hash of the ticket's own public_token -- proof the sender
 * actually received a real email about this exact ticket, since that
 * fragment is never guessable or exposed anywhere else (see
 * ticket_thread_message_id()) -- and, always, the From address must match
 * the ticket's requester_email. Returns the ticket row, or null if either
 * check fails or the ticket no longer exists.
 */
function verify_ticket_reply_target(array $target, string $fromAddress): ?array
{
    $stmt = db()->prepare('SELECT * FROM tickets WHERE id = ?');
    $stmt->execute([$target['ticketId']]);
    $ticket = $stmt->fetch();
    if (!$ticket) {
        return null;
    }

    if ($target['fragment'] !== null) {
        if (empty($ticket['public_token'])) {
            return null;
        }
        $expected = ticket_reply_verification_fragment($ticket['public_token']);
        if (!hash_equals($expected, $target['fragment'])) {
            return null;
        }
    }

    if ($fromAddress === '' || strcasecmp(trim($fromAddress), trim($ticket['requester_email'])) !== 0) {
        return null;
    }

    return $ticket;
}

/** Pulls the bare address out of a "From" header value, e.g. "Jane <jane@example.com>" -> "jane@example.com". */
function extract_email_address(string $headerValue): string
{
    if (preg_match('/<([^<>]+)>/', $headerValue, $m) === 1) {
        return trim($m[1]);
    }
    return trim($headerValue);
}

/**
 * Flags $uid \Seen and best-effort moves it into $targetMailbox (expected to
 * already exist -- this app has no CREATE MAILBOX support). A failed move is
 * logged but never fatal: the sync cursor already guarantees this message
 * won't be reprocessed either way, the move is only there so a human
 * glancing at the mailbox in a real mail client can see what happened.
 */
function mark_inbound_message_handled(Client $client, string $targetMailbox, int $uid): void
{
    $client->setFlags([$uid], ['\\Seen'], 'add');

    if ($targetMailbox === '') {
        return;
    }

    try {
        $client->move([$uid], $targetMailbox);
    } catch (Throwable $e) {
        error_log('IMAP: could not move message UID ' . $uid . " to \"{$targetMailbox}\": " . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// MIME content extraction
// ---------------------------------------------------------------------------

/**
 * Picks the best reply text out of $structure (preferring text/plain over
 * text/html) and pulls out every attachment part, downloading and storing
 * each one. Returns [$text, $attachments], where each attachment is
 * ['path' => ..., 'filename' => ..., 'mimeType' => ..., 'size' => ...].
 *
 * @return array{0: string, 1: array<int, array<string, mixed>>}
 */
function extract_message_content(Client $client, int $uid, ?MessagePart $structure): array
{
    if ($structure === null) {
        return ['', []];
    }

    if (!$structure->isMultipart()) {
        if ($structure->isAttachment()) {
            // The whole message is a single non-text part with no body text
            // of its own (e.g. a bare PDF sent with no message).
            $attachment = fetch_and_store_attachment($client, $uid, $structure, 'TEXT');
            return ['', $attachment !== null ? [$attachment] : []];
        }

        $raw = fetch_body_part($client, $uid, 'TEXT');
        $text = decode_part_content($raw, $structure);
        if (strcasecmp($structure->subtype, 'html') === 0) {
            $text = html_to_text($text);
        }
        return [$text, []];
    }

    $plainPart = null;
    $htmlPart = null;
    $attachmentParts = [];
    collect_message_parts($structure, $plainPart, $htmlPart, $attachmentParts);

    $text = '';
    if ($plainPart !== null) {
        $text = decode_part_content(fetch_body_part($client, $uid, $plainPart->partNumber), $plainPart);
    } elseif ($htmlPart !== null) {
        $text = html_to_text(decode_part_content(fetch_body_part($client, $uid, $htmlPart->partNumber), $htmlPart));
    }

    $attachments = [];
    foreach ($attachmentParts as $part) {
        $attachment = fetch_and_store_attachment($client, $uid, $part);
        if ($attachment !== null) {
            $attachments[] = $attachment;
        }
    }

    return [$text, $attachments];
}

/**
 * Walks a BODYSTRUCTURE tree, recursing into every multipart node (mixed,
 * alternative, related, ...) and sorting each leaf into $plainPart/$htmlPart
 * (first one found wins) or $attachmentParts.
 *
 * @param array<int, MessagePart> $attachmentParts
 */
function collect_message_parts(MessagePart $part, ?MessagePart &$plainPart, ?MessagePart &$htmlPart, array &$attachmentParts): void
{
    if ($part->isMultipart()) {
        foreach ($part->children as $child) {
            collect_message_parts($child, $plainPart, $htmlPart, $attachmentParts);
        }
        return;
    }

    if ($part->isAttachment()) {
        $attachmentParts[] = $part;
        return;
    }

    if ($plainPart === null && strcasecmp($part->type, 'text') === 0 && strcasecmp($part->subtype, 'plain') === 0) {
        $plainPart = $part;
    } elseif ($htmlPart === null && strcasecmp($part->type, 'text') === 0 && strcasecmp($part->subtype, 'html') === 0) {
        $htmlPart = $part;
    }
}

/** Fetches one body section and returns its raw (still-encoded) content, or '' if the fetch came back empty. */
function fetch_body_part(Client $client, int $uid, string $section): string
{
    $messages = $client->fetchBody([$uid], $section);
    $message = $messages[0] ?? null;
    return $message?->getBodyPart($section) ?? '';
}

/** Decodes a fetched body part's Content-Transfer-Encoding and charset into a plain UTF-8 string. */
function decode_part_content(string $raw, MessagePart $part): string
{
    $decoded = match (strtoupper($part->encoding)) {
        'BASE64' => (string) base64_decode($raw, false),
        'QUOTED-PRINTABLE' => quoted_printable_decode($raw),
        default => $raw, // 7BIT, 8BIT, BINARY, or unspecified -- already plain text
    };

    $charset = null;
    foreach ($part->parameters as $key => $value) {
        if (strcasecmp($key, 'charset') === 0) {
            $charset = $value;
            break;
        }
    }

    if ($charset !== null && strcasecmp($charset, 'utf-8') !== 0 && strcasecmp($charset, 'us-ascii') !== 0) {
        $converted = @mb_convert_encoding($decoded, 'UTF-8', $charset);
        if ($converted !== false) {
            $decoded = $converted;
        }
    }

    return $decoded;
}

/** Strips HTML tags down to plain text, turning common block-level breaks into newlines first so paragraphs don't run together. */
function html_to_text(string $html): string
{
    $withBreaks = preg_replace('#<(br|/p|/div|/tr|/li)\s*/?>#i', "\n", $html) ?? $html;
    $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES, 'UTF-8');
    $collapsed = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
    return trim($collapsed);
}

/**
 * A best-effort attempt at dropping the quoted history a reply carries
 * along (the classic ">"-prefixed chain, "On ... wrote:", "-----Original
 * Message-----", or an Outlook-style "From:/Sent:" quoted-headers block) --
 * not perfect, but good enough to keep a ticket's conversation readable.
 * Everything from the first recognized marker onward is dropped.
 */
function strip_quoted_reply(string $text): string
{
    $lines = explode("\n", str_replace("\r\n", "\n", $text));
    $kept = [];

    foreach ($lines as $i => $line) {
        if (preg_match('/^\s*>/', $line) === 1) {
            break;
        }
        if (preg_match('/^\s*On .+ wrote:\s*$/i', $line) === 1) {
            break;
        }
        if (preg_match('/^-{2,}\s*Original Message\s*-{2,}/i', $line) === 1) {
            break;
        }
        if (preg_match('/^\s*From:\s*\S/i', $line) === 1 && preg_match('/^\s*Sent:\s*\S/i', $lines[$i + 1] ?? '') === 1) {
            break;
        }
        $kept[] = $line;
    }

    return trim(implode("\n", $kept));
}

/**
 * Downloads $part's content, validates it against the allow-list/size cap
 * below, and stores it in uploads/ (same directory ticket screenshots use).
 * Returns null (logging why) rather than throwing on a rejected or
 * unreadable attachment, so one bad attachment doesn't lose the whole reply.
 */
function fetch_and_store_attachment(Client $client, int $uid, MessagePart $part, ?string $sectionOverride = null): ?array
{
    $section = $sectionOverride ?? $part->partNumber;
    $raw = fetch_body_part($client, $uid, $section);

    $decoded = match (strtoupper($part->encoding)) {
        'BASE64' => base64_decode($raw, true),
        'QUOTED-PRINTABLE' => quoted_printable_decode($raw),
        default => $raw,
    };

    if ($decoded === false || $decoded === '') {
        return null;
    }

    if (strlen($decoded) > IMAP_ATTACHMENT_MAX_BYTES) {
        error_log('IMAP: dropped an attachment over the size limit (' . strlen($decoded) . ' bytes).');
        return null;
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($decoded) ?: 'application/octet-stream';
    $extension = IMAP_ATTACHMENT_MIME_EXTENSIONS[$mime] ?? null;
    if ($extension === null) {
        error_log('IMAP: dropped an attachment of an unsupported type (' . $mime . ').');
        return null;
    }

    $uploadDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        return null;
    }

    $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
    if (file_put_contents($uploadDir . '/' . $storedName, $decoded) === false) {
        return null;
    }

    return [
        'path' => 'uploads/' . $storedName,
        'filename' => $part->attachmentFilename() ?? $storedName,
        'mimeType' => $mime,
        'size' => strlen($decoded),
    ];
}
