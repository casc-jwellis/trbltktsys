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

/**
 * Whether the ticket_assigned_users/ticket_assigned_groups tables exist yet
 * (migration 007). Guards every ticket-assignment function below so a
 * pending migration degrades gracefully instead of crashing ticket
 * submission, the ticket queue, or a ticket's detail page.
 */
function ticket_assignments_supported(): bool
{
    static $result = null;
    if ($result === null) {
        $result = table_exists('ticket_assigned_users');
    }
    return $result;
}

/**
 * Seeds a newly submitted ticket's assignment from whichever users/groups
 * are configured (Admin Settings -> Categories) to handle its category.
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

    $stmt = db()->prepare('INSERT INTO ticket_assigned_users (ticket_id, user_id) SELECT ?, user_id FROM user_categories WHERE category_id = ?');
    $stmt->execute([$ticketId, $categoryId]);

    $stmt = db()->prepare('INSERT INTO ticket_assigned_groups (ticket_id, group_id) SELECT ?, group_id FROM group_categories WHERE category_id = ?');
    $stmt->execute([$ticketId, $categoryId]);
}

/** Non-locked agents plus, if given, any already-assigned users (so a now-locked account currently assigned to a ticket still shows up). */
function assignable_users(array $includeUserIds = []): array
{
    $placeholders = implode(',', array_fill(0, count($includeUserIds), '?'));
    $sql = 'SELECT id, full_name FROM users WHERE is_locked = 0';
    if ($placeholders !== '') {
        $sql .= ' OR id IN (' . $placeholders . ')';
    }
    $stmt = db()->prepare($sql . ' ORDER BY full_name');
    $stmt->execute($includeUserIds);
    return $stmt->fetchAll();
}

function assignable_groups(): array
{
    return db()->query('SELECT id, name FROM agent_groups ORDER BY name')->fetchAll();
}

function ticket_assigned_user_ids(int $ticketId): array
{
    if (!ticket_assignments_supported()) {
        return [];
    }
    $stmt = db()->prepare('SELECT user_id FROM ticket_assigned_users WHERE ticket_id = ?');
    $stmt->execute([$ticketId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
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

/** Filters submitted group/category/user IDs down to ones that actually exist. */
function valid_ids_from_post(array $submitted, array $validRows): array
{
    $validIds = array_column($validRows, 'id');
    $ids = array_map('intval', $submitted);
    return array_values(array_intersect($ids, $validIds));
}

/** Replaces a ticket's full set of assigned users/groups with the given IDs. */
function save_ticket_assignments(int $ticketId, array $userIds, array $groupIds): void
{
    if (!ticket_assignments_supported()) {
        return;
    }

    $pdo = db();
    $pdo->prepare('DELETE FROM ticket_assigned_users WHERE ticket_id = ?')->execute([$ticketId]);
    $stmt = $pdo->prepare('INSERT INTO ticket_assigned_users (ticket_id, user_id) VALUES (?, ?)');
    foreach ($userIds as $userId) {
        $stmt->execute([$ticketId, $userId]);
    }

    $pdo->prepare('DELETE FROM ticket_assigned_groups WHERE ticket_id = ?')->execute([$ticketId]);
    $stmt = $pdo->prepare('INSERT INTO ticket_assigned_groups (ticket_id, group_id) VALUES (?, ?)');
    foreach ($groupIds as $groupId) {
        $stmt->execute([$ticketId, $groupId]);
    }
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
