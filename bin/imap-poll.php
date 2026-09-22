<?php
// CLI entry point: pulls in and processes new submitter replies over IMAP.
// Run this on a schedule (Windows Task Scheduler, cron, etc.) -- there's no
// built-in scheduler in the app itself. Safe to run as often as you like;
// it no-ops immediately if IMAP is disabled in Admin Settings, and otherwise
// only processes messages newer than its last run (see includes/imap.php).
//
// Usage: php bin/imap-poll.php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script is for command-line use only.');
}

require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/mail.php';
require_once __DIR__ . '/../includes/imap.php';

if (!imap_settings_supported()) {
    fwrite(STDERR, "IMAP settings table not found -- visit migrate.php in a browser first.\n");
    exit(1);
}

try {
    $summary = process_inbound_mail();
} catch (Throwable $e) {
    fwrite(STDERR, 'IMAP poll failed: ' . $e->getMessage() . "\n");
    exit(1);
}

if ($summary['skipped']) {
    echo "IMAP is disabled in Admin Settings -- nothing to do.\n";
    exit(0);
}

printf(
    "Processed %d repl%s, %d unmatched message%s, %d empty repl%s, %d error%s.\n",
    $summary['processed'],
    $summary['processed'] === 1 ? 'y' : 'ies',
    $summary['unmatched'],
    $summary['unmatched'] === 1 ? '' : 's',
    $summary['empty'],
    $summary['empty'] === 1 ? 'y' : 'ies',
    $summary['errors'],
    $summary['errors'] === 1 ? '' : 's'
);

exit($summary['errors'] > 0 ? 1 : 0);
