<?php
/**
 * Cancel a booking - Free slot if status was 'parked', set booking to 'cancelled'
 * Saves changes to database.
 */
define('PARKING_ACCESS', true);
require_once dirname(__DIR__) . '/config/init.php';
requireLogin();
if (isAdmin()) { header('Location: ' . BASE_URL . '/index.php'); exit; }

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/user/booking-history.php');
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare('SELECT id, status, parking_slot_id FROM bookings WHERE id = ? AND user_id = ?');
$stmt->execute([$id, currentUserId()]);
$row = $stmt->fetch();

if ($row && in_array($row['status'], ['pending', 'parked'])) {
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE bookings SET status = ? WHERE id = ?')->execute(['cancelled', $id]);
        if ($row['status'] === 'parked') {
            $pdo->prepare('UPDATE parking_slots SET status = ? WHERE id = ?')->execute(['available', $row['parking_slot_id']]);
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
    }
}

setAlert('Booking cancelled and slot freed.', 'info');
header('Location: ' . BASE_URL . '/user/booking-history.php');
exit;
