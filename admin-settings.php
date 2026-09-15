<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/admin.php';
require_admin();

$activeTab = in_array($_GET['tab'] ?? '', ['groups', 'categories', 'responses', 'database'], true) ? $_GET['tab'] : 'users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'Your session expired. Please try again.');
        header('Location: admin-settings.php?tab=' . $activeTab);
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');
    $allGroups = all_groups();
    $allCategories = all_categories();

    if ($action === 'create_user') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
        $isAdmin = isset($_POST['is_admin']);
        $groupIds = valid_ids_from_post($_POST['groups'] ?? [], $allGroups);

        $errors = [];
        if ($username === '' || strlen($username) > 50) {
            $errors[] = 'Please enter a username (up to 50 characters).';
        }
        if ($fullName === '') {
            $errors[] = 'Please enter a full name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (strlen($phone) > 30) {
            $errors[] = 'Phone number is too long (30 characters max).';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $passwordConfirm) {
            $errors[] = 'Passwords do not match.';
        }

        if (!$errors) {
            try {
                db()->beginTransaction();

                $stmt = db()->prepare(
                    'INSERT INTO users (username, password_hash, full_name, email, phone, is_admin) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $username,
                    password_hash($password, PASSWORD_DEFAULT),
                    $fullName,
                    $email,
                    $phone !== '' ? $phone : null,
                    $isAdmin ? 1 : 0,
                ]);
                $newUserId = (int) db()->lastInsertId();

                $groupStmt = db()->prepare('INSERT INTO user_agent_groups (user_id, group_id) VALUES (?, ?)');
                foreach ($groupIds as $groupId) {
                    $groupStmt->execute([$newUserId, $groupId]);
                }

                db()->commit();
                flash('success', 'Created user "' . $fullName . '".');
            } catch (PDOException $e) {
                db()->rollBack();
                flash('error', $e->getCode() === '23000'
                    ? 'That username is already in use.'
                    : 'Could not create the user.');
            }
        } else {
            flash('error', implode(' ', $errors));
        }
    } elseif ($action === 'update_user') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $username = trim((string) ($_POST['username'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $isAdmin = isset($_POST['is_admin']);
        $groupIds = valid_ids_from_post($_POST['groups'] ?? [], $allGroups);

        $errors = [];
        if ($username === '' || strlen($username) > 50) {
            $errors[] = 'Please enter a username (up to 50 characters).';
        }
        if ($fullName === '') {
            $errors[] = 'Please enter a full name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (strlen($phone) > 30) {
            $errors[] = 'Phone number is too long (30 characters max).';
        }
        if ($newPassword !== '' && strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        }

        $willBeActiveAdmin = $isAdmin && !user_is_disabled($userId);
        if (!$willBeActiveAdmin && user_is_active_admin($userId) && active_admin_count($userId) === 0) {
            $errors[] = 'At least one active administrator is required — cannot remove Administrator from the last one.';
        }

        if (!$errors) {
            try {
                db()->beginTransaction();

                $phone = $phone !== '' ? $phone : null;

                if ($newPassword !== '') {
                    $stmt = db()->prepare(
                        'UPDATE users SET username = ?, full_name = ?, email = ?, phone = ?, is_admin = ?, password_hash = ? WHERE id = ?'
                    );
                    $stmt->execute([$username, $fullName, $email, $phone, $isAdmin ? 1 : 0, password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
                } else {
                    $stmt = db()->prepare('UPDATE users SET username = ?, full_name = ?, email = ?, phone = ?, is_admin = ? WHERE id = ?');
                    $stmt->execute([$username, $fullName, $email, $phone, $isAdmin ? 1 : 0, $userId]);
                }

                db()->prepare('DELETE FROM user_agent_groups WHERE user_id = ?')->execute([$userId]);
                $groupStmt = db()->prepare('INSERT INTO user_agent_groups (user_id, group_id) VALUES (?, ?)');
                foreach ($groupIds as $groupId) {
                    $groupStmt->execute([$userId, $groupId]);
                }

                db()->commit();

                if ($userId === current_user_id()) {
                    $_SESSION['user']['username'] = $username;
                    $_SESSION['user']['full_name'] = $fullName;
                }

                flash('success', 'Updated user "' . $fullName . '".');
            } catch (PDOException $e) {
                db()->rollBack();
                flash('error', $e->getCode() === '23000'
                    ? 'That username is already in use.'
                    : 'Could not update the user.');
            }
        } else {
            flash('error', implode(' ', $errors));
        }
    } elseif ($action === 'toggle_disabled') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $disable = (string) ($_POST['disabled'] ?? '') === '1';

        if ($userId === current_user_id()) {
            flash('error', 'You cannot disable or enable your own account.');
        } elseif ($disable && user_is_active_admin($userId) && active_admin_count($userId) === 0) {
            flash('error', 'At least one active administrator is required — cannot disable the last one.');
        } else {
            $stmt = db()->prepare('UPDATE users SET disabled = ? WHERE id = ?');
            $stmt->execute([$disable ? 1 : 0, $userId]);
            flash('success', $disable ? 'User disabled.' : 'User enabled.');
        }
    } elseif ($action === 'delete_user') {
        $userId = (int) ($_POST['user_id'] ?? 0);

        if ($userId === current_user_id()) {
            flash('error', 'You cannot remove your own account.');
        } elseif (user_is_active_admin($userId) && active_admin_count($userId) === 0) {
            flash('error', 'At least one active administrator is required — cannot remove the last one.');
        } else {
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
            flash('success', 'User removed.');
        }
    } elseif ($action === 'create_group') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($name === '' || strlen($name) > 60) {
            flash('error', 'Please enter a group name (up to 60 characters).');
        } else {
            try {
                $stmt = db()->prepare('INSERT INTO agent_groups (name, description) VALUES (?, ?)');
                $stmt->execute([$name, $description !== '' ? $description : null]);
                flash('success', 'Created group "' . $name . '".');
            } catch (PDOException $e) {
                flash('error', $e->getCode() === '23000'
                    ? 'A group with that name already exists.'
                    : 'Could not create the group.');
            }
        }
        $activeTab = 'groups';
    } elseif ($action === 'update_group') {
        $groupId = (int) ($_POST['group_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $categoryIds = valid_ids_from_post($_POST['categories'] ?? [], $allCategories);

        if ($name === '' || strlen($name) > 60) {
            flash('error', 'Please enter a group name (up to 60 characters).');
        } else {
            try {
                db()->beginTransaction();

                $stmt = db()->prepare('UPDATE agent_groups SET name = ?, description = ? WHERE id = ?');
                $stmt->execute([$name, $description !== '' ? $description : null, $groupId]);

                db()->prepare('DELETE FROM group_categories WHERE group_id = ?')->execute([$groupId]);
                $categoryStmt = db()->prepare('INSERT INTO group_categories (group_id, category_id) VALUES (?, ?)');
                foreach ($categoryIds as $categoryId) {
                    $categoryStmt->execute([$groupId, $categoryId]);
                }

                db()->commit();
                flash('success', 'Updated group "' . $name . '".');
            } catch (PDOException $e) {
                db()->rollBack();
                flash('error', $e->getCode() === '23000'
                    ? 'A group with that name already exists.'
                    : 'Could not update the group.');
            }
        }
        $activeTab = 'groups';
    } elseif ($action === 'delete_group') {
        $groupId = (int) ($_POST['group_id'] ?? 0);
        db()->prepare('DELETE FROM agent_groups WHERE id = ?')->execute([$groupId]);
        flash('success', 'Group removed.');
        $activeTab = 'groups';
    } elseif ($action === 'create_category') {
        $name = trim((string) ($_POST['name'] ?? ''));

        if ($name === '' || strlen($name) > 50) {
            flash('error', 'Please enter a category name (up to 50 characters).');
        } else {
            try {
                $stmt = db()->prepare('INSERT INTO categories (name) VALUES (?)');
                $stmt->execute([$name]);
                flash('success', 'Created category "' . $name . '".');
            } catch (PDOException $e) {
                flash('error', $e->getCode() === '23000'
                    ? 'A category with that name already exists.'
                    : 'Could not create the category.');
            }
        }
        $activeTab = 'categories';
    } elseif ($action === 'update_category') {
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));

        if ($name === '' || strlen($name) > 50) {
            flash('error', 'Please enter a category name (up to 50 characters).');
        } else {
            try {
                $stmt = db()->prepare('UPDATE categories SET name = ? WHERE id = ?');
                $stmt->execute([$name, $categoryId]);
                flash('success', 'Updated category "' . $name . '".');
            } catch (PDOException $e) {
                flash('error', $e->getCode() === '23000'
                    ? 'A category with that name already exists.'
                    : 'Could not update the category.');
            }
        }
        $activeTab = 'categories';
    } elseif ($action === 'delete_category') {
        $categoryId = (int) ($_POST['category_id'] ?? 0);

        if (count($allCategories) <= 1) {
            flash('error', 'At least one category is required — the ticket form needs somewhere to send submissions.');
        } else {
            try {
                db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$categoryId]);
                flash('success', 'Category removed.');
            } catch (PDOException $e) {
                flash('error', $e->getCode() === '23000'
                    ? 'This category is used by existing tickets and cannot be removed.'
                    : 'Could not remove the category.');
            }
        }
        $activeTab = 'categories';
    } elseif ($action === 'create_canned_response') {
        if (!canned_responses_supported()) {
            flash('error', 'The database is out of date. Visit migrate.php to enable canned responses.');
        } else {
            $title = trim((string) ($_POST['title'] ?? ''));
            $body = trim((string) ($_POST['body'] ?? ''));

            if ($title === '' || strlen($title) > 100) {
                flash('error', 'Please enter a title (up to 100 characters).');
            } elseif ($body === '') {
                flash('error', 'Please enter the response text.');
            } else {
                $stmt = db()->prepare('INSERT INTO canned_responses (title, body) VALUES (?, ?)');
                $stmt->execute([$title, $body]);
                flash('success', 'Created canned response "' . $title . '".');
            }
        }
        $activeTab = 'responses';
    } elseif ($action === 'update_canned_response') {
        if (!canned_responses_supported()) {
            flash('error', 'The database is out of date. Visit migrate.php to enable canned responses.');
        } else {
            $responseId = (int) ($_POST['response_id'] ?? 0);
            $title = trim((string) ($_POST['title'] ?? ''));
            $body = trim((string) ($_POST['body'] ?? ''));

            if ($title === '' || strlen($title) > 100) {
                flash('error', 'Please enter a title (up to 100 characters).');
            } elseif ($body === '') {
                flash('error', 'Please enter the response text.');
            } else {
                $stmt = db()->prepare('UPDATE canned_responses SET title = ?, body = ? WHERE id = ?');
                $stmt->execute([$title, $body, $responseId]);
                flash('success', 'Updated canned response "' . $title . '".');
            }
        }
        $activeTab = 'responses';
    } elseif ($action === 'delete_canned_response') {
        if (canned_responses_supported()) {
            $responseId = (int) ($_POST['response_id'] ?? 0);
            db()->prepare('DELETE FROM canned_responses WHERE id = ?')->execute([$responseId]);
            flash('success', 'Canned response removed.');
        }
        $activeTab = 'responses';
    } elseif ($action === 'purge_database') {
        $confirmation = trim((string) ($_POST['confirmation'] ?? ''));

        if ($confirmation !== 'DELETE EVERYTHING') {
            flash('error', 'Type "DELETE EVERYTHING" exactly to confirm the purge.');
            $activeTab = 'database';
        } else {
            try {
                purge_all_data();
                logout();
                header('Location: install.php');
                exit;
            } catch (PDOException $e) {
                flash('error', 'Could not purge the database.');
                $activeTab = 'database';
            }
        }
    }

    header('Location: admin-settings.php?tab=' . $activeTab);
    exit;
}

$allGroups = all_groups();
$allCategories = all_categories();
$users = all_users_with_groups();
$userGroupMap = user_group_id_map();
$groupCategoryMap = group_category_id_map();
$groups = $allGroups;
$categories = all_categories_with_counts();
$cannedResponses = all_canned_responses();

$pageTitle = 'Admin Settings';
require __DIR__ . '/includes/header.php';
?>

<div class="mb-4">
    <h1 class="h3 mb-1">Admin Settings</h1>
    <p class="text-body-secondary mb-0">Manage helpdesk staff accounts, groups, categories, and canned responses.</p>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'users' ? 'active' : '' ?>" href="admin-settings.php?tab=users">User Management</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'groups' ? 'active' : '' ?>" href="admin-settings.php?tab=groups">Groups</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'categories' ? 'active' : '' ?>" href="admin-settings.php?tab=categories">Categories</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'responses' ? 'active' : '' ?>" href="admin-settings.php?tab=responses">Responses</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'database' ? 'active' : '' ?>" href="admin-settings.php?tab=database">Database</a>
    </li>
</ul>

<?php if ($activeTab === 'groups'): ?>
    <?php require __DIR__ . '/includes/admin-groups-tab.php'; ?>
<?php elseif ($activeTab === 'categories'): ?>
    <?php require __DIR__ . '/includes/admin-categories-tab.php'; ?>
<?php elseif ($activeTab === 'responses'): ?>
    <?php require __DIR__ . '/includes/admin-responses-tab.php'; ?>
<?php elseif ($activeTab === 'database'): ?>
    <?php require __DIR__ . '/includes/admin-database-tab.php'; ?>
<?php else: ?>
    <?php require __DIR__ . '/includes/admin-users-tab.php'; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
