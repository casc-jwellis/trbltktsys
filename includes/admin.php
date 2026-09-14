<?php
// Helper functions for admin-settings.php. Not autoloaded by bootstrap.php —
// only admin-settings.php needs these, so it requires this file directly.

function all_roles(): array
{
    return db()->query('SELECT id, name FROM roles ORDER BY id')->fetchAll();
}

function all_groups(): array
{
    return db()->query(
        'SELECT g.id, g.name, g.description, COUNT(DISTINCT ug.user_id) AS member_count,
                GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ", ") AS category_names
         FROM agent_groups g
         LEFT JOIN user_agent_groups ug ON ug.group_id = g.id
         LEFT JOIN group_categories gc ON gc.group_id = g.id
         LEFT JOIN categories c ON c.id = gc.category_id
         GROUP BY g.id
         ORDER BY g.name'
    )->fetchAll();
}

function all_categories_with_counts(): array
{
    return db()->query(
        'SELECT c.id, c.name,
                COUNT(DISTINCT t.id) AS ticket_count,
                COUNT(DISTINCT uc.user_id) AS user_count,
                COUNT(DISTINCT gc.group_id) AS group_count
         FROM categories c
         LEFT JOIN tickets t ON t.category = c.name
         LEFT JOIN user_categories uc ON uc.category_id = c.id
         LEFT JOIN group_categories gc ON gc.category_id = c.id
         GROUP BY c.id
         ORDER BY c.name'
    )->fetchAll();
}

function all_users_with_roles_and_groups(): array
{
    return db()->query(
        'SELECT u.id, u.username, u.full_name, u.email, u.phone, u.is_locked, u.created_at,
                GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ", ") AS role_names,
                GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ", ") AS group_names,
                GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ", ") AS category_names
         FROM users u
         LEFT JOIN user_roles ur ON ur.user_id = u.id
         LEFT JOIN roles r ON r.id = ur.role_id
         LEFT JOIN user_agent_groups ug ON ug.user_id = u.id
         LEFT JOIN agent_groups g ON g.id = ug.group_id
         LEFT JOIN user_categories uc ON uc.user_id = u.id
         LEFT JOIN categories c ON c.id = uc.category_id
         GROUP BY u.id
         ORDER BY u.full_name'
    )->fetchAll();
}

function user_role_id_map(): array
{
    $map = [];
    foreach (db()->query('SELECT user_id, role_id FROM user_roles') as $row) {
        $map[(int) $row['user_id']][] = (int) $row['role_id'];
    }
    return $map;
}

function user_group_id_map(): array
{
    $map = [];
    foreach (db()->query('SELECT user_id, group_id FROM user_agent_groups') as $row) {
        $map[(int) $row['user_id']][] = (int) $row['group_id'];
    }
    return $map;
}

function user_category_id_map(): array
{
    $map = [];
    foreach (db()->query('SELECT user_id, category_id FROM user_categories') as $row) {
        $map[(int) $row['user_id']][] = (int) $row['category_id'];
    }
    return $map;
}

function group_category_id_map(): array
{
    $map = [];
    foreach (db()->query('SELECT group_id, category_id FROM group_categories') as $row) {
        $map[(int) $row['group_id']][] = (int) $row['category_id'];
    }
    return $map;
}

function active_admin_count(?int $excludeUserId = null): int
{
    $sql = "SELECT COUNT(*) FROM users u
            INNER JOIN user_roles ur ON ur.user_id = u.id
            INNER JOIN roles r ON r.id = ur.role_id
            WHERE r.name = 'Administrator' AND u.is_locked = 0";
    $params = [];
    if ($excludeUserId !== null) {
        $sql .= ' AND u.id != ?';
        $params[] = $excludeUserId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function user_is_active_admin(int $userId): bool
{
    return in_array('Administrator', user_role_names($userId), true)
        && !user_is_locked($userId);
}

function user_is_locked(int $userId): bool
{
    $stmt = db()->prepare('SELECT is_locked FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row && (int) $row['is_locked'] === 1;
}

/** Filters submitted role/group IDs down to ones that actually exist. */
function valid_ids_from_post(array $submitted, array $validRows): array
{
    $validIds = array_column($validRows, 'id');
    $ids = array_map('intval', $submitted);
    return array_values(array_intersect($ids, $validIds));
}

/**
 * Deletes every ticket, requester, staff account, and group. Roles and
 * categories are left in place (they're fixed reference/config data, not
 * user content) so the app can still assign them — and ticket submission
 * still has categories to offer — once someone goes through initial setup
 * again.
 */
function purge_all_data(): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM tickets');
        $pdo->exec('DELETE FROM requesters');
        $pdo->exec('DELETE FROM users');
        $pdo->exec('DELETE FROM agent_groups');
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        throw $e;
    }
}
