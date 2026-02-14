<?php
/**
 * AJAX endpoint: return booking/vehicle details as JSON
 */
define('PARKING_ACCESS', true);
require_once dirname(__DIR__) . '/config/init.php';
requireAdmin();

header('Content-Type: application/json');

$booking_id = (int) ($_GET['booking_id'] ?? 0);
if (!$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking id']);
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare(
    "SELECT b.id, b.status, b.entry_time, b.booked_at, b.planned_entry_time, b.exit_time,
            IF(b.planned_entry_time IS NOT NULL AND b.exit_time IS NOT NULL, TIMESTAMPDIFF(MINUTE, b.planned_entry_time, b.exit_time), NULL) AS planned_duration_minutes,
            ps.slot_number, v.plate_number, v.model, v.color, u.full_name, u.phone
     FROM bookings b
     JOIN vehicles v ON b.vehicle_id = v.id
     JOIN parking_slots ps ON b.parking_slot_id = ps.id
     JOIN users u ON b.user_id = u.id
     WHERE b.id = ? LIMIT 1"
);
$stmt->execute([$booking_id]);
$row = $stmt->fetch();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Booking not found']);
    exit;
}

// Normalize data
$data = [
    'id' => (int) $row['id'],
    'status' => $row['status'] ?? 'pending',
    'entry_time' => $row['entry_time'] ? date('g:i A', strtotime($row['entry_time'])) : ($row['booked_at'] ? date('g:i A', strtotime($row['booked_at'])) : null),
    'duration_minutes' => (int) ($row['planned_duration_minutes'] ?? 0),
    'slot_number' => $row['slot_number'] ?? null,
    'plate_number' => $row['plate_number'] ?? null,
    'model' => $row['model'] ?? null,
    'color' => $row['color'] ?? null,
    'owner' => $row['full_name'] ?? null,
    'phone' => $row['phone'] ?? null,
];

echo json_encode(['success' => true, 'data' => $data]);

?>
