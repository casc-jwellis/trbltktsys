<?php
// The migration runner lives in Admin Settings -> Database now (administrators
// only). This stays as a redirect so old bookmarks and links still land
// somewhere useful; the admin login page handles anyone who isn't signed in.
header('Location: admin-settings.php?tab=database');
exit;
