<?php

const TICKET_PRIORITIES = ['Low', 'Medium', 'High', 'Urgent'];
const TICKET_STATUSES   = ['Open', 'In Progress', 'Resolved', 'Closed'];

/** Categories are managed in the database (Admin Settings -> Categories). */
function all_categories(): array
{
    return db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
}

function category_names(): array
{
    return array_column(all_categories(), 'name');
}

/** Whether the canned_responses table exists yet (migration 009). */
function canned_responses_supported(): bool
{
    static $result = null;
    if ($result === null) {
        $result = table_exists('canned_responses');
    }
    return $result;
}

/** Canned responses are managed in the database (Admin Settings -> Responses). */
function all_canned_responses(): array
{
    if (!canned_responses_supported()) {
        return [];
    }
    return db()->query('SELECT id, title, body FROM canned_responses ORDER BY title')->fetchAll();
}

/**
 * Whether the ticket_assigned_groups table exists yet (migration 007).
 * Guards every ticket-assignment function below so a pending migration
 * degrades gracefully instead of crashing ticket submission, the ticket
 * queue, or a ticket's detail page.
 */
function ticket_assignments_supported(): bool
{
    static $result = null;
    if ($result === null) {
        $result = table_exists('ticket_assigned_groups');
    }
    return $result;
}

/**
 * Seeds a newly submitted ticket's assignment from whichever groups are
 * configured (Admin Settings -> Categories) to handle its category.
 */
function assign_ticket_by_category(int $ticketId, string $category): void
{
    if (!ticket_assignments_supported()) {
        return;
    }

    $stmt = db()->prepare('SELECT id FROM categories WHERE name = ?');
    $stmt->execute([$category]);
    $categoryId = $stmt->fetchColumn();

    if ($categoryId === false) {
        return;
    }

    $stmt = db()->prepare('INSERT INTO ticket_assigned_groups (ticket_id, group_id) SELECT ?, group_id FROM group_categories WHERE category_id = ?');
    $stmt->execute([$ticketId, $categoryId]);
}

function assignable_groups(): array
{
    return db()->query('SELECT id, name FROM agent_groups ORDER BY name')->fetchAll();
}

/** Whether tickets.assigned_agent_id exists yet (migration 017). */
function ticket_assigned_agent_supported(): bool
{
    static $result = null;
    if ($result === null) {
        $result = column_exists('tickets', 'assigned_agent_id');
    }
    return $result;
}

/** Active (non-disabled) agents, for the "Assigned Agent" picker. */
function active_agents(): array
{
    return db()->query('SELECT id, full_name FROM users WHERE disabled = 0 ORDER BY full_name')->fetchAll();
}

/** A ticket's single assigned agent (id + name), or null if unassigned or unsupported. */
function ticket_assigned_agent(int $ticketId): ?array
{
    if (!ticket_assigned_agent_supported()) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT u.id, u.full_name FROM tickets t
         JOIN users u ON u.id = t.assigned_agent_id
         WHERE t.id = ?'
    );
    $stmt->execute([$ticketId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Sets or clears a ticket's single assigned agent. $agentId null unassigns. */
function save_ticket_assigned_agent(int $ticketId, ?int $agentId): void
{
    if (!ticket_assigned_agent_supported()) {
        return;
    }
    db()->prepare('UPDATE tickets SET assigned_agent_id = ? WHERE id = ?')->execute([$agentId, $ticketId]);
}

function ticket_assigned_group_ids(int $ticketId): array
{
    if (!ticket_assignments_supported()) {
        return [];
    }
    $stmt = db()->prepare('SELECT group_id FROM ticket_assigned_groups WHERE ticket_id = ?');
    $stmt->execute([$ticketId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Whether a user can view a ticket: they're its assigned agent, they belong
 * to one of its assigned groups, or (for admins) always. Fails closed if the
 * assignment table isn't there yet (pending migration).
 */
function user_can_view_ticket(int $ticketId, int $userId): bool
{
    $agent = ticket_assigned_agent($ticketId);
    if ($agent && (int) $agent['id'] === $userId) {
        return true;
    }

    $groupIds = ticket_assigned_group_ids($ticketId);
    if (!$groupIds) {
        return false;
    }

    $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
    $stmt = db()->prepare("SELECT COUNT(*) FROM user_agent_groups WHERE user_id = ? AND group_id IN ({$placeholders})");
    $stmt->execute([$userId, ...$groupIds]);
    return (int) $stmt->fetchColumn() > 0;
}

/** Filters submitted group/category/user IDs down to ones that actually exist. */
function valid_ids_from_post(array $submitted, array $validRows): array
{
    $validIds = array_column($validRows, 'id');
    $ids = array_map('intval', $submitted);
    return array_values(array_intersect($ids, $validIds));
}

/** Replaces a ticket's full set of assigned groups with the given IDs. */
function save_ticket_assignments(int $ticketId, array $groupIds): void
{
    if (!ticket_assignments_supported()) {
        return;
    }

    $pdo = db();
    $pdo->prepare('DELETE FROM ticket_assigned_groups WHERE ticket_id = ?')->execute([$ticketId]);
    $stmt = $pdo->prepare('INSERT INTO ticket_assigned_groups (ticket_id, group_id) VALUES (?, ?)');
    foreach ($groupIds as $groupId) {
        $stmt->execute([$ticketId, $groupId]);
    }
}

/** Whether tickets.last_submitter_activity_at/viewed_at exist yet (migration 020). */
function ticket_activity_tracking_supported(): bool
{
    static $result = null;
    if ($result === null) {
        $result = column_exists('tickets', 'last_submitter_activity_at');
    }
    return $result;
}

/**
 * Records that the submitter did something worth an agent's attention -- a
 * reply via the web link or an inbound email (a brand new ticket is already
 * covered by last_submitter_activity_at's own column default) -- so the
 * ticket shows a "New" indicator until an agent opens it. See
 * ticket_is_new(). A no-op if the tracking columns don't exist yet.
 */
function mark_ticket_submitter_activity(int $ticketId): void
{
    if (!ticket_activity_tracking_supported()) {
        return;
    }
    db()->prepare('UPDATE tickets SET last_submitter_activity_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$ticketId]);
}

/**
 * Clears a ticket's "New" indicator -- called whenever any agent opens the
 * ticket page. Shared across all agents rather than tracked per agent: once
 * anyone has looked, it's no longer "New" for anyone.
 */
function mark_ticket_viewed(int $ticketId): void
{
    if (!ticket_activity_tracking_supported()) {
        return;
    }
    db()->prepare('UPDATE tickets SET viewed_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$ticketId]);
}

/** Whether $ticket has submitter activity since it was last viewed by an agent. */
function ticket_is_new(array $ticket): bool
{
    if (empty($ticket['last_submitter_activity_at'])) {
        return false;
    }
    if (empty($ticket['viewed_at'])) {
        return true;
    }
    return strtotime($ticket['last_submitter_activity_at']) > strtotime($ticket['viewed_at']);
}

/** Whether tickets.public_token exists yet (migration 014). */
function ticket_public_tokens_supported(): bool
{
    static $result = null;
    if ($result === null) {
        $result = column_exists('tickets', 'public_token');
    }
    return $result;
}

/**
 * The scheme+host+directory this app is being served from, e.g.
 * "https://helpdesk.example.com/trbltktsys" -- used to build absolute links
 * for emails, which (unlike in-page links) can't rely on a relative URL.
 */
function base_url(): string
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') === '443';
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $scheme . '://' . $host . $dir;
}

function ticket_public_link(string $publicToken): string
{
    return base_url() . '/ticket-status.php?token=' . $publicToken;
}

function ticket_staff_link(int $ticketId): string
{
    return base_url() . '/ticket.php?id=' . $ticketId;
}

/**
 * A stable Message-ID (RFC 5322 §3.6.4 format) shared by every email sent
 * about one ticket, so mail clients (Gmail included) thread them together.
 * The first email for a ticket sets this as its own Message-ID; every later
 * one references it via In-Reply-To/References -- see send_ticket_email().
 *
 * When the ticket has a public_token, a short fragment derived from it (see
 * ticket_reply_verification_fragment() -- never the token itself) is folded
 * into the local part. A reply-capable mail client always echoes the
 * parent's Message-ID back verbatim in its own In-Reply-To/References
 * header, so an inbound reply carrying the right ticket ID *and* the right
 * fragment could only have originated from a real email this app actually
 * sent about that exact ticket -- see bin/imap-poll.php, which requires
 * that match (plus a sender-address check) before accepting a reply as
 * genuine. The token itself is deliberately kept out of this header so it
 * never leaks into agent-facing copies of these emails.
 */
function ticket_thread_message_id(int $ticketId): string
{
    $host = parse_url(base_url(), PHP_URL_HOST) ?: 'localhost';
    $host = preg_replace('/[^A-Za-z0-9.-]/', '', $host) ?: 'localhost';

    $suffix = '';
    if (ticket_public_tokens_supported()) {
        $stmt = db()->prepare('SELECT public_token FROM tickets WHERE id = ?');
        $stmt->execute([$ticketId]);
        $token = $stmt->fetchColumn();
        if ($token) {
            $suffix = '-' . ticket_reply_verification_fragment($token);
        }
    }

    return '<ticket-' . $ticketId . $suffix . '@' . $host . '>';
}

/**
 * A short, one-way fragment derived from a ticket's public_token, folded
 * into its thread Message-ID (see ticket_thread_message_id()) to let an
 * inbound reply be verified as genuine without ever putting the token
 * itself in a header. Namespaced with a fixed prefix so this can't be
 * confused with (or trivially reversed into) the actual public reply-link
 * token.
 */
function ticket_reply_verification_fragment(string $publicToken): string
{
    return substr(hash('sha256', 'reply-verify:' . $publicToken), 0, 20);
}

/**
 * Who to notify about a ticket by email: its assigned agent alone, if one is
 * set and still active with an email address -- otherwise every active user
 * in its assigned groups, deduplicated. Once a ticket has an owner, group
 * mates no longer need to hear about every reply on it.
 */
function ticket_assigned_agent_emails(int $ticketId): array
{
    if (ticket_assigned_agent_supported()) {
        $stmt = db()->prepare(
            'SELECT u.email, u.full_name
             FROM tickets t
             JOIN users u ON u.id = t.assigned_agent_id
             WHERE t.id = ? AND u.disabled = 0 AND u.email IS NOT NULL AND u.email != ""'
        );
        $stmt->execute([$ticketId]);
        $agent = $stmt->fetch();
        if ($agent) {
            return [$agent];
        }
    }

    $groupIds = ticket_assigned_group_ids($ticketId);
    if (!$groupIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
    $stmt = db()->prepare(
        "SELECT DISTINCT u.email, u.full_name
         FROM users u
         JOIN user_agent_groups ug ON ug.user_id = u.id
         WHERE ug.group_id IN ({$placeholders}) AND u.disabled = 0 AND u.email IS NOT NULL AND u.email != ''"
    );
    $stmt->execute($groupIds);
    return $stmt->fetchAll();
}

/** Whether the ticket_comments table exists yet (migration 008). */
function ticket_comments_supported(): bool
{
    static $result = null;
    if ($result === null) {
        $result = table_exists('ticket_comments');
    }
    return $result;
}

/**
 * A ticket's conversation thread in chronological order: agent responses
 * meant for the submitter, and internal notes meant only for other staff.
 */
function ticket_comments(int $ticketId): array
{
    if (!ticket_comments_supported()) {
        return [];
    }
    $stmt = db()->prepare(
        'SELECT tc.id, tc.body, tc.is_internal, tc.created_at, u.full_name AS author_name
         FROM ticket_comments tc
         LEFT JOIN users u ON u.id = tc.user_id
         WHERE tc.ticket_id = ?
         ORDER BY tc.created_at ASC, tc.id ASC'
    );
    $stmt->execute([$ticketId]);
    return $stmt->fetchAll();
}

/** Whether the ticket_comment_attachments table exists yet (migration 019). */
function ticket_comment_attachments_supported(): bool
{
    static $result = null;
    if ($result === null) {
        $result = table_exists('ticket_comment_attachments');
    }
    return $result;
}

/**
 * Every attachment on any comment in a ticket's thread, grouped by comment
 * id, in one query rather than one per comment. Currently only populated by
 * inbound mail (see includes/imap.php).
 *
 * @return array<int, array<int, array<string, mixed>>>
 */
function ticket_comment_attachments_by_ticket(int $ticketId): array
{
    if (!ticket_comment_attachments_supported() || !ticket_comments_supported()) {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT a.* FROM ticket_comment_attachments a
         JOIN ticket_comments c ON c.id = a.comment_id
         WHERE c.ticket_id = ?
         ORDER BY a.id ASC'
    );
    $stmt->execute([$ticketId]);

    $byComment = [];
    foreach ($stmt->fetchAll() as $attachment) {
        $byComment[(int) $attachment['comment_id']][] = $attachment;
    }
    return $byComment;
}

/**
 * $userId is null for a comment posted by the submitter (via
 * ticket-status.php or an inbound email reply), who isn't a logged-in user.
 */
function add_ticket_comment(int $ticketId, ?int $userId, string $body, bool $isInternal): void
{
    if (!ticket_comments_supported()) {
        return;
    }
    $stmt = db()->prepare(
        'INSERT INTO ticket_comments (ticket_id, user_id, body, is_internal) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$ticketId, $userId, $body, $isInternal ? 1 : 0]);
}

/**
 * A ticket's conversation thread as the submitter is allowed to see it --
 * responses only, never internal notes. Filtered in SQL (not just at
 * render time) so internal-note content is never even loaded for this path.
 */
function ticket_public_comments(int $ticketId): array
{
    if (!ticket_comments_supported()) {
        return [];
    }
    $stmt = db()->prepare(
        'SELECT tc.id, tc.body, tc.created_at, u.full_name AS author_name
         FROM ticket_comments tc
         LEFT JOIN users u ON u.id = tc.user_id
         WHERE tc.ticket_id = ? AND tc.is_internal = 0
         ORDER BY tc.created_at ASC, tc.id ASC'
    );
    $stmt->execute([$ticketId]);
    return $stmt->fetchAll();
}

const ATTACHMENT_MAX_BYTES = 5 * 1024 * 1024;
const ATTACHMENT_MIME_EXTENSIONS = [
    'image/png'  => 'png',
    'image/jpeg' => 'jpg',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

/**
 * Validates and stores an uploaded screenshot from a $_FILES entry.
 * Returns the stored relative path on success, or null if no file was
 * uploaded. Throws InvalidArgumentException on a validation failure.
 */
function store_ticket_attachment(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('The screenshot failed to upload. Please try again.');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('The screenshot failed to upload. Please try again.');
    }

    if ($file['size'] > ATTACHMENT_MAX_BYTES) {
        throw new InvalidArgumentException('The screenshot is too large (5 MB max).');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(ATTACHMENT_MIME_EXTENSIONS[$mime])) {
        throw new InvalidArgumentException('The screenshot must be a PNG, JPEG, GIF, or WEBP image.');
    }

    $uploadDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new InvalidArgumentException('The screenshot failed to upload. Please try again.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . ATTACHMENT_MIME_EXTENSIONS[$mime];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
        throw new InvalidArgumentException('The screenshot failed to upload. Please try again.');
    }

    return 'uploads/' . $filename;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function app_name(): string
{
    static $config = null;
    if ($config === null) {
        $path = __DIR__ . '/../config/config.php';
        $config = file_exists($path) ? require $path : [];
    }
    return $config['app']['name'] ?? 'Helpdesk';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

function flash(string $key, ?string $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return;
    }

    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

function status_badge_class(string $status): string
{
    return match ($status) {
        'Open'        => 'text-bg-primary',
        'In Progress' => 'text-bg-warning',
        'Resolved'    => 'text-bg-success',
        'Closed'      => 'text-bg-secondary',
        default       => 'text-bg-secondary',
    };
}

function priority_badge_class(string $priority): string
{
    return match ($priority) {
        'Low'    => 'text-bg-light',
        'Medium' => 'text-bg-info',
        'High'   => 'text-bg-warning',
        'Urgent' => 'text-bg-danger',
        default  => 'text-bg-light',
    };
}
