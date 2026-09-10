<?php

const TICKET_CATEGORIES = ['General', 'Hardware', 'Software', 'Network', 'Account Access', 'Other'];
const TICKET_PRIORITIES = ['Low', 'Medium', 'High', 'Urgent'];
const TICKET_STATUSES   = ['Open', 'In Progress', 'Resolved', 'Closed'];

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function app_name(): string
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/config.php';
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
