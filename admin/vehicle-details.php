<?php
/**
 * Admin - View booking/vehicle details (placeholder)
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
$row = $pdo->prepare("
    SELECT b.id, b.entry_time, ps.slot_number, v.plate_number, v.model, v.color, u.full_name, u.phone
    FROM bookings b
    JOIN vehicles v ON b.vehicle_id = v.id
    JOIN parking_slots ps ON b.parking_slot_id = ps.id
    JOIN users u ON b.user_id = u.id
    WHERE b.id = ? AND b.status = 'parked'
");
$row->execute([$booking_id]);
$v = $row->fetch();

$page_title = 'Vehicle Details';
$current_page = 'admin-monitor';
require dirname(__DIR__) . '/includes/header.php';
?>

<div style="max-width: 600px; margin: 0 auto;">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><strong>Vehicle Details</strong></div>
        <div class="card-body">
            <?php if ($v): ?>
                <table class="table table-sm mb-0">
                    <tr><td class="text-muted">Slot</td><td><?= htmlspecialchars($v['slot_number']) ?></td></tr>
                    <tr><td class="text-muted">Plate</td><td><?= htmlspecialchars($v['plate_number']) ?></td></tr>
                    <tr><td class="text-muted">Model / Color</td><td><?= htmlspecialchars($v['model'] ?? '-') ?> / <?= htmlspecialchars($v['color'] ?? '-') ?></td></tr>
                    <tr><td class="text-muted">Driver</td><td><?= htmlspecialchars($v['full_name']) ?></td></tr>
                    <tr><td class="text-muted">Phone</td><td><?= htmlspecialchars($v['phone'] ?? '-') ?></td></tr>
                    <tr><td class="text-muted">Entry Time</td><td><?= $v['entry_time'] ? date('M j, Y g:i A', strtotime($v['entry_time'])) : '—' ?></td></tr>
                </table>
            <?php else: ?>
                <p class="text-muted mb-0">Booking not found or no longer parked.</p>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/admin/monitor.php" class="btn btn-outline-secondary mt-3">Back to Monitor</a>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
