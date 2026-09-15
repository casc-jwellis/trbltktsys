<?php

function schema_installed(): bool
{
    $stmt = db()->query("SHOW TABLES LIKE 'users'");
    return (bool) $stmt->fetch();
}

function is_installed(): bool
{
    try {
        return (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function install_schema(): void
{
    run_sql_file(__DIR__ . '/../schema.sql');
}

function column_exists(string $table, string $column): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function table_exists(string $table): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Every migration this app has ever shipped, in order, with a check that
 * tells us whether its effect is already present in the database — rather
 * than a bookkeeping table, since some installs applied earlier migrations
 * by hand before this runner existed.
 */
function available_migrations(): array
{
    return [
        [
            'file'    => '001_admin_roles_groups.sql',
            'label'   => 'Account locking, roles, and groups',
            // Checked via agent_groups rather than the is_locked column this
            // migration added, since migration 010 later renamed that column
            // to disabled -- agent_groups is untouched and still a reliable sentinel.
            'applied' => fn (): bool => table_exists('agent_groups'),
        ],
        [
            'file'    => '002_user_contact_info.sql',
            'label'   => 'User email and phone number fields',
            'applied' => fn (): bool => column_exists('users', 'email'),
        ],
        [
            'file'    => '003_ticket_attachments.sql',
            'label'   => 'Ticket screenshot attachments',
            'applied' => fn (): bool => column_exists('tickets', 'attachment_path'),
        ],
        [
            'file'    => '004_requesters.sql',
            'label'   => 'Ticket requester directory',
            'applied' => fn (): bool => table_exists('requesters'),
        ],
        [
            'file'    => '005_categories.sql',
            'label'   => 'Manageable ticket categories, assignable to users and groups',
            'applied' => fn (): bool => table_exists('categories'),
        ],
        [
            'file'    => '006_admin_flag.sql',
            'label'   => 'Replace roles with a single is_admin flag',
            'applied' => fn (): bool => column_exists('users', 'is_admin'),
        ],
        [
            'file'    => '007_ticket_assignments.sql',
            'label'   => 'Multi user/group ticket assignment',
            // Checked via ticket_assigned_groups rather than
            // ticket_assigned_users, which migration 012 later dropped.
            'applied' => fn (): bool => table_exists('ticket_assigned_groups'),
        ],
        [
            'file'    => '008_ticket_comments.sql',
            'label'   => 'Ticket response/internal note thread',
            'applied' => fn (): bool => table_exists('ticket_comments'),
        ],
        [
            'file'    => '009_canned_responses.sql',
            'label'   => 'Canned responses',
            'applied' => fn (): bool => table_exists('canned_responses'),
        ],
        [
            'file'    => '010_disabled_flag.sql',
            'label'   => 'Rename is_locked to disabled',
            'applied' => fn (): bool => column_exists('users', 'disabled'),
        ],
        [
            'file'    => '011_remove_user_categories.sql',
            'label'   => 'Remove per-user category permissions (groups only)',
            'applied' => fn (): bool => !table_exists('user_categories'),
        ],
        [
            'file'    => '012_remove_ticket_assigned_users.sql',
            'label'   => 'Remove per-user ticket assignment (groups only)',
            'applied' => fn (): bool => !table_exists('ticket_assigned_users'),
        ],
        [
            'file'    => '013_smtp_settings.sql',
            'label'   => 'SMTP email settings',
            'applied' => fn (): bool => table_exists('smtp_settings'),
        ],
        [
            'file'    => '014_ticket_public_token.sql',
            'label'   => 'Per-ticket public access token (submitter status/reply links)',
            'applied' => fn (): bool => column_exists('tickets', 'public_token'),
        ],
    ];
}

function pending_migrations(): array
{
    return array_values(array_filter(
        available_migrations(),
        fn (array $migration): bool => !call_user_func($migration['applied'])
    ));
}

function run_migration(string $filename): void
{
    $path = __DIR__ . '/../migrations/' . $filename;
    if (!is_file($path)) {
        throw new RuntimeException("Migration file not found: {$filename}");
    }
    run_sql_file($path);
}

function run_sql_file(string $path): void
{
    $sql = file_get_contents($path);
    // Strip `--` line comments first so a semicolon written in prose (e.g.
    // "existing rows; back them up first") can't be mistaken for a statement
    // boundary by the naive split below.
    $sql = preg_replace('/--[^\n]*/', '', $sql);
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $pdo = db();
    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
}

/**
 * Writes config/config.php from validated setup-form input. Values are
 * embedded via var_export() so nothing from the form can break out of
 * the generated PHP array literal.
 */
function write_config(array $db, array $app): void
{
    $php = "<?php\n\nreturn " . var_export(['db' => $db, 'app' => $app], true) . ";\n";

    $path = __DIR__ . '/../config/config.php';
    if (@file_put_contents($path, $php, LOCK_EX) === false) {
        throw new RuntimeException(
            'Could not write config/config.php. Make sure the config/ directory is writable by the web server, then try again.'
        );
    }
}
