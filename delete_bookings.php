<?php
/**
 * Delete all bookings and reset database
 */
define('PARKING_ACCESS', true);
require_once __DIR__ . '/config/init.php';

$pdo = getDB();

try {
    $pdo->beginTransaction();
    
    // Delete all bookings
    $pdo->query('DELETE FROM bookings');
    
    // Delete all payments
    $pdo->query('DELETE FROM payments');
    
    // Reset all parking slots to available
    $pdo->query('UPDATE parking_slots SET status = "available"');
    
    $pdo->commit();
    
    echo '<div style="padding: 2rem; text-align: center; font-family: Arial, sans-serif;">';
    echo '<div style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 8px; display: inline-block;">';
    echo '<h3 style="margin-top: 0;">✓ Success</h3>';
    echo '<p>All bookings and payments have been deleted.</p>';
    echo '<p>All parking slots have been reset to available status.</p>';
    echo '</div>';
    echo '<p style="margin-top: 2rem;"><a href="' . BASE_URL . '/admin/parking-history.php" style="color: #16a34a; text-decoration: none; font-weight: 600;">Back to Parking History</a></p>';
    echo '</div>';
} catch (Exception $e) {
    $pdo->rollBack();
    echo '<div style="padding: 2rem; text-align: center; font-family: Arial, sans-serif;">';
    echo '<div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; display: inline-block;">';
    echo '<h3 style="margin-top: 0;">✗ Error</h3>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div>';
    echo '</div>';
}
?>
