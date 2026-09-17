<?php

declare(strict_types=1);

/**
 * Zero-dependency PSR-4-style autoloader for the Tehimap\Imap\ namespace.
 * No Composer, no vendor/ directory — just require this file once.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Tehimap\\Imap\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});
