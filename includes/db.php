<?php

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $config = require __DIR__ . '/../config/config.php';

        $pdo = new PDO(
            $config['db']['dsn'],
            $config['db']['user'],
            $config['db']['pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

        // Every timestamp column (created_at, viewed_at, ...) is a MySQL
        // TIMESTAMP, which stores an exact moment but reads and writes it in
        // the session's time zone. Pin that to UTC so what comes back never
        // depends on how the database server happens to be configured; PHP
        // converts to the site's timezone for display (see db_time()).
        $pdo->exec("SET time_zone = '+00:00'");
    }

    return $pdo;
}
