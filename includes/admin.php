<?php
// Helper functions for admin-settings.php. Not autoloaded by bootstrap.php —
// only admin-settings.php needs these, so it requires this file directly.

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

function all_users_with_groups_and_categories(): array
{
    return db()->query(
        'SELECT u.id, u.username, u.full_name, u.email, u.phone, u.is_admin, u.disabled, u.created_at,
                GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ", ") AS group_names,
                GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ", ") AS category_names
         FROM users u
         LEFT JOIN user_agent_groups ug ON ug.user_id = u.id
         LEFT JOIN agent_groups g ON g.id = ug.group_id
         LEFT JOIN user_categories uc ON uc.user_id = u.id
         LEFT JOIN categories c ON c.id = uc.category_id
         GROUP BY u.id
         ORDER BY u.full_name'
    )->fetchAll();
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
    $sql = 'SELECT COUNT(*) FROM users WHERE is_admin = 1 AND disabled = 0';
    $params = [];
    if ($excludeUserId !== null) {
        $sql .= ' AND id != ?';
        $params[] = $excludeUserId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function user_is_active_admin(int $userId): bool
{
    $stmt = db()->prepare('SELECT is_admin, disabled FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row && (int) $row['is_admin'] === 1 && (int) $row['disabled'] === 0;
}

function user_is_disabled(int $userId): bool
{
    $stmt = db()->prepare('SELECT disabled FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row && (int) $row['disabled'] === 1;
}

/**
 * Deletes every ticket, requester, staff account, and group. Categories are
 * left in place (they're fixed config data, not user content) so ticket
 * submission still has something to offer once someone goes through initial
 * setup again.
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
