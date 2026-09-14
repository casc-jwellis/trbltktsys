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
    $sql = file_get_contents(__DIR__ . '/../schema.sql');
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
