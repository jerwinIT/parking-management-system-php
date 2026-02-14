<?php
/**
 * Delete a car - Only if not in an active (parked) booking. Saves change to database.
 */
define('PARKING_ACCESS', true);
require_once dirname(__DIR__) . '/config/init.php';
requireLogin();
if (isAdmin()) { header('Location: ' . BASE_URL . '/index.php'); exit; }

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/user/register-car.php');
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare('SELECT id FROM vehicles WHERE id = ? AND user_id = ?');
$stmt->execute([$id, currentUserId()]);
if (!$stmt->fetch()) {
    header('Location: ' . BASE_URL . '/user/register-car.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM bookings WHERE vehicle_id = ? AND status = ?');
$stmt->execute([$id, 'parked']);
if ($stmt->fetch()) {
    setAlert('Cannot delete: this vehicle is currently parked. Cancel the booking first.', 'warning');
    header('Location: ' . BASE_URL . '/user/register-car.php');
    exit;
}

$pdo->prepare('DELETE FROM vehicles WHERE id = ? AND user_id = ?')->execute([$id, currentUserId()]);
setAlert('Vehicle removed successfully.', 'success');
header('Location: ' . BASE_URL . '/user/register-car.php');
exit;
