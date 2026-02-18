<?php
/**
 * Booking helper functions
 */
if (!defined('PARKING_ACCESS')) define('PARKING_ACCESS', true);

/**
 * Return true if the slot is occupied now (parked or overlapping booking)
 */
function isSlotOccupiedNow(PDO $pdo, $slotId, $now = null) {
    $now = $now ?? date('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM bookings WHERE parking_slot_id = ? AND planned_entry_time IS NOT NULL AND exit_time IS NOT NULL AND planned_entry_time <= ? AND exit_time > ? AND status IN (?, ?, ? )"
    );
    $stmt->execute([$slotId, $now, $now, 'parked', 'active', 'pending']);
    return ((int)$stmt->fetchColumn()) > 0;
}

/**
 * Get upcoming bookings for a slot starting from a date (defaults to today)
 */
function getUpcomingBookings(PDO $pdo, $slotId, $fromDate = null, $limit = 10) {
    $from = $fromDate ? (is_string($fromDate) ? $fromDate : date('Y-m-d', strtotime($fromDate))) : date('Y-m-d');
    $stmt = $pdo->prepare(
        "SELECT planned_entry_time, exit_time, status FROM bookings WHERE parking_slot_id = ? AND DATE(planned_entry_time) >= ? AND planned_entry_time IS NOT NULL AND exit_time IS NOT NULL AND status IN (?, ?, ?) ORDER BY planned_entry_time ASC LIMIT ?"
    );
    $stmt->execute([$slotId, $from, 'pending', 'confirmed', 'active', (int)$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get parked booking info (vehicle and entry_time) if any for slot
 */
function getParkedBookingInfo(PDO $pdo, $slotId) {
    $stmt = $pdo->prepare(
        "SELECT b.id, b.entry_time, v.plate_number, u.full_name FROM bookings b JOIN vehicles v ON b.vehicle_id = v.id JOIN users u ON b.user_id = u.id WHERE b.parking_slot_id = ? AND b.status = 'parked' ORDER BY b.entry_time DESC LIMIT 1"
    );
    $stmt->execute([$slotId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Count currently parked vehicles (status='parked')
 */
function countCurrentlyParked(PDO $pdo) {
    return (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'parked'")->fetchColumn();
}

/**
 * Count active reservations (status='pending')
 */
function countActiveReservations(PDO $pdo) {
    return (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();
}

return true;
