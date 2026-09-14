<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/setup.php';

$configPath = __DIR__ . '/../config/config.php';
$configExists = file_exists($configPath);
$config = $configExists ? require $configPath : [];
date_default_timezone_set($config['app']['timezone'] ?? 'UTC');

// Send every page except the installer itself to install.php until the
// database credentials, schema, and first admin account are all in place.
if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
    if (!$configExists) {
        header('Location: install.php');
        exit;
    }

    try {
        $installed = is_installed();
    } catch (PDOException $e) {
        http_response_code(500);
        exit('Could not connect to the database. Check the credentials in config/config.php.');
    }

    if (!$installed) {
        header('Location: install.php');
        exit;
    }
}
