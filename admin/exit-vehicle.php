<?php
/**
 * Admin - Mark vehicle as exited: set booking completed, free slot
 */
define('PARKING_ACCESS', true);
require_once dirname(__DIR__) . '/config/init.php';
requireAdmin();

$booking_id = (int) ($_GET['booking_id'] ?? 0);
if (!$booking_id) {
    header('Location: ' . BASE_URL . '/admin/monitor.php');
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare('SELECT parking_slot_id FROM bookings WHERE id = ? AND status = ?');
$stmt->execute([$booking_id, 'parked']);
$row = $stmt->fetch();
if ($row) {
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE bookings SET status = ?, exit_time = NOW() WHERE id = ?')->execute(['completed', $booking_id]);
        $pdo->prepare('UPDATE parking_slots SET status = ? WHERE id = ?')->execute(['available', $row['parking_slot_id']]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
    }
}
header('Location: ' . BASE_URL . '/admin/monitor.php');
exit;
