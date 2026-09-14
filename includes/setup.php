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
            'applied' => fn (): bool => column_exists('users', 'is_locked'),
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
            'applied' => fn (): bool => table_exists('ticket_assigned_users'),
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
