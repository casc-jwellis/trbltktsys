<?php
// Copy this file to config.php and fill in your real values.
// config.php is gitignored so real credentials never get committed.

return [
    'db' => [
        'dsn'  => 'mysql:host=127.0.0.1;dbname=trbltktsys;charset=utf8mb4',
        'user' => 'trbltktsys_user',
        'pass' => 'changeme',
    ],
    'app' => [
        'name'     => 'Carl Albert Helpdesk',
        'timezone' => 'America/Chicago',
        // Public address of this helpdesk, no trailing slash. Used for the links in emails; required for emails
        // sent by bin/imap-poll.php, which has no browser request to learn it from.
        'url'      => 'https://helpdesk.example.edu',
    ],
];
