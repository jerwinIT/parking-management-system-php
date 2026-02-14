<?php
/**
 * Dev helper: ensure default roles exist and fix orphaned users
 * Run from browser: http://localhost/PARKING_MANAGEMENT_SYSTEM/dev/add_missing_roles.php
 * Or run CLI: php dev/add_missing_roles.php
 */
define('PARKING_ACCESS', true);
require_once dirname(__DIR__) . '/config/init.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = getDB();
    $pdo->beginTransaction();

    // Ensure admin and user roles exist with consistent ids
    $sql = "INSERT INTO roles (id, role_name, description) VALUES
    (1,'admin','System administrator - full access'),
    (2,'user','Driver / User - can book parking slots')
    ON DUPLICATE KEY UPDATE role_name = VALUES(role_name), description = VALUES(description)";
    $pdo->exec($sql);

    // Find the user role id
    $stmt = $pdo->prepare('SELECT id FROM roles WHERE LOWER(role_name) = LOWER(?) LIMIT 1');
    $stmt->execute(['user']);
    $userRoleId = $stmt->fetchColumn();
    if (!$userRoleId) {
        throw new Exception('Failed to ensure user role exists.');
    }

    // Update any users that reference a missing role to use the user role
    $update = $pdo->prepare('UPDATE users u LEFT JOIN roles r ON u.role_id = r.id SET u.role_id = ? WHERE r.id IS NULL');
    $update->execute([$userRoleId]);
    $affected = $update->rowCount();

    $pdo->commit();

    echo "Success: ensured roles. user_role_id={$userRoleId}\n";
    echo "Orphaned users updated: {$affected}\n";
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Done.\n";
