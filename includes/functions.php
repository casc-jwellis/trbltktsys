<?php

const TICKET_CATEGORIES = ['General', 'Hardware', 'Software', 'Network', 'Account Access', 'Other'];
const TICKET_PRIORITIES = ['Low', 'Medium', 'High', 'Urgent'];
const TICKET_STATUSES   = ['Open', 'In Progress', 'Resolved', 'Closed'];

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
