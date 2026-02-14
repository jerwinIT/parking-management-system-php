<?php
/**
 * End Parking - Mark booking as completed and free the slot
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

// Only allow ending if booking is in 'parked' status
// If admin has already ended it (status='completed'), user cannot end it again
if ($row && $row['status'] === 'parked') {
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE bookings SET status = ?, exit_time = NOW() WHERE id = ?')->execute(['completed', $id]);
        $pdo->prepare('UPDATE parking_slots SET status = ? WHERE id = ?')->execute(['available', $row['parking_slot_id']]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
    }
} elseif ($row && $row['status'] === 'completed') {
    // Booking already completed (admin already ended it)
    setAlert('This parking session was already ended by the admin.', 'warning');
    header('Location: ' . BASE_URL . '/user/booking-history.php');
    exit;
}

setAlert('Parking session ended successfully. Slot has been freed.', 'success');
header('Location: ' . BASE_URL . '/user/booking-history.php');
exit;
