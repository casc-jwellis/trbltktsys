<?php
// The browser-tab icon, in the theme color chosen in Admin Settings -> Theme.
// Deliberately doesn't load includes/bootstrap.php -- no session cookie or
// install redirect for an image. The page links here with ?c=<color> so a
// color change gets a new URL and isn't hidden behind the old cached icon.

require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/setup.php';
require __DIR__ . '/includes/theme.php';

header('Content-Type: image/svg+xml');
header('Cache-Control: public, max-age=86400');
echo theme_favicon_svg(theme_settings()['color']);
