<?php
/**
 * Booking Details - Display detailed information about a booking
 */
define('PARKING_ACCESS', true);
require_once dirname(__DIR__) . '/config/init.php';
requireLogin();
if (isAdmin()) { header('Location: ' . BASE_URL . '/index.php'); exit; }

$page_title = 'Booking Details';
$current_page = 'booking-details';

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/user/booking-history.php');
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("
    SELECT b.id, b.status, b.booked_at, b.entry_time, b.exit_time, b.planned_entry_time, 
           v.plate_number, v.model, v.color, ps.slot_number,
           u.full_name, u.phone,
           TIMESTAMPDIFF(MINUTE, b.entry_time, COALESCE(b.exit_time, NOW())) AS duration_minutes,
           p.amount, p.payment_method, p.payment_subtype, p.wallet_contact, p.account_number, p.payer_name, p.payment_status, p.paid_at
    FROM bookings b
    JOIN vehicles v ON b.vehicle_id = v.id
    JOIN parking_slots ps ON b.parking_slot_id = ps.id
    JOIN users u ON b.user_id = u.id
    LEFT JOIN payments p ON p.booking_id = b.id
    WHERE b.id = ? AND b.user_id = ?
");
$stmt->execute([$id, currentUserId()]);
$booking = $stmt->fetch();

if (!$booking) {
    header('Location: ' . BASE_URL . '/user/booking-history.php');
    exit;
}

// Format slot label
function slotLabel($slot_number) {
    if (preg_match('/^([A-Z])(\d+)$/', $slot_number, $m)) return $m[1] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT);
    return $slot_number;
}

// prefer actual entry_time, otherwise planned_entry_time or booked_at for display
$entry_source = $booking['entry_time'] ?? $booking['planned_entry_time'] ?? $booking['booked_at'] ?? null;
$exit = $booking['exit_time'] ?? null;

$mins = (int) ($booking['duration_minutes'] ?? 0);
$duration = '';
if ($mins >= 60) {
    $duration = floor($mins/60) . 'h ' . ($mins%60) . 'm';
} else if ($mins > 0) {
    $duration = $mins . 'm';
}

require dirname(__DIR__) . '/includes/header.php';
?>

<style>
.booking-details-page {
    max-width: 100%;
    width: 100%;
    margin: 0;
    padding-left: 0.25rem;
    padding-right: 0.25rem;
}
.details-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
.details-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #f3f4f6;
}
.details-head h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #111;
    margin: 0;
}
.details-badge {
    padding: 0.5rem 1rem;
    border-radius: 9999px;
    font-weight: 600;
    font-size: 0.9rem;
    text-align: center;
}
.details-badge.pending { background: #ffedd5; color: #c2410c; }
.details-badge.parked { background: #166534; color: #fff; }
.details-badge.completed { background: #dcfce7; color: #4d7c5c; }

.details-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}
@media (max-width: 768px) { .details-grid { grid-template-columns: 1fr; } }

.details-item {
    display: flex;
    flex-direction: column;
}
.details-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
}
.details-value {
    font-size: 1rem;
    font-weight: 600;
    color: #111;
}
.details-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1rem;
}
.btn {
    padding: 0.6rem 1.2rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.875rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-primary { background: #22c55e; color: #fff; }
.btn-primary:hover { background: #16a34a; }
.btn-secondary { background: #f3f4f6; color: #374151; }
.btn-secondary:hover { background: #e5e7eb; }
.btn-danger { background: #ef4444; color: #fff; }
.btn-danger:hover { background: #dc2626; }
</style>

<div class="booking-details-page">
    <a href="<?= BASE_URL ?>/user/booking-history.php" class="d-inline-flex align-items-center text-decoration-none text-dark mb-4" style="color: #111;">
        <i class="bi bi-arrow-left me-1"></i> Back to Bookings
    </a>

    <div class="details-card">
        <div class="details-head">
            <h2>Booking #<?= str_pad($booking['id'], 6, '0', STR_PAD_LEFT) ?></h2>
            <span class="details-badge <?= strtolower($booking['status']) ?>"><?= ucfirst($booking['status']) ?></span>
        </div>

        <div class="details-grid">
            <div class="details-item">
                <div class="details-label">Parking Slot</div>
                <div class="details-value" style="color: #16a34a; font-size: 1.3rem;"><?= htmlspecialchars(slotLabel($booking['slot_number'])) ?></div>
            </div>
            <div class="details-item">
                <div class="details-label">Vehicle</div>
                <div class="details-value"><?= htmlspecialchars($booking['model']) ?></div>
            </div>
            <div class="details-item">
                <div class="details-label">License Plate</div>
                <div class="details-value"><?= htmlspecialchars($booking['plate_number']) ?></div>
            </div>
            <div class="details-item">
                <div class="details-label">Vehicle Color</div>
                <div class="details-value"><?= htmlspecialchars($booking['color']) ?></div>
            </div>
            <div class="details-item">
                <div class="details-label">Booked Date</div>
                <div class="details-value"><?= date('F j, Y \a\t g:i A', strtotime($booking['booked_at'])) ?></div>
            </div>
            <?php if ($entry_source): ?>
            <div class="details-item">
                <div class="details-label">Entry Time</div>
                <div class="details-value"><?= date('F j, Y \a\t g:i A', strtotime($entry_source)) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($booking['exit_time']): ?>
            <div class="details-item">
                <div class="details-label">Exit Time</div>
                <div class="details-value"><?= date('F j, Y \a\t g:i A', strtotime($booking['exit_time'])) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($duration): ?>
            <div class="details-item">
                <div class="details-label">Duration</div>
                <div class="details-value"><?= htmlspecialchars($duration) ?></div>
            </div>
            <?php endif; ?>
            <div class="details-item">
                <div class="details-label">Your Name</div>
                <div class="details-value"><?= htmlspecialchars($booking['full_name']) ?></div>
            </div>
            <div class="details-item">
                <div class="details-label">Phone</div>
                <div class="details-value"><?= htmlspecialchars($booking['phone']) ?></div>
            </div>
        </div>

        <?php if ($booking['amount']): ?>
        <div style="border-top: 1px solid #f3f4f6; padding-top: 1.5rem; margin-top: 1.5rem;">
            <h5 style="font-weight: 700; margin-bottom: 1rem;">Payment Information</h5>
            <div class="details-grid">
                <div class="details-item">
                    <div class="details-label">Amount Paid</div>
                    <div class="details-value" style="color: #16a34a; font-size: 1.3rem;">&#8369;<?= number_format($booking['amount'], 2) ?></div>
                </div>
                <div class="details-item">
                    <div class="details-label">Payment Method</div>
                    <div class="details-value"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $booking['payment_method'] ?? '—'))) ?></div>
                </div>
                <?php if ($booking['payment_subtype']): ?>
                <div class="details-item">
                    <div class="details-label">Payment Type</div>
                    <div class="details-value"><?= htmlspecialchars($booking['payment_subtype']) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($booking['account_number']): ?>
                <div class="details-item">
                    <div class="details-label">Account</div>
                    <div class="details-value"><?= htmlspecialchars($booking['account_number']) ?></div>
                </div>
                <?php endif; ?>
                <div class="details-item">
                    <div class="details-label">Payment Status</div>
                    <div class="details-value"><?= htmlspecialchars(ucfirst($booking['payment_status'] ?? 'pending')) ?></div>
                </div>
                <?php if ($booking['paid_at']): ?>
                <div class="details-item">
                    <div class="details-label">Paid Date</div>
                    <div class="details-value"><?= date('F j, Y \a\t g:i A', strtotime($booking['paid_at'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="details-actions">
            <a href="<?= BASE_URL ?>/user/booking-history.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <?php if ($booking['status'] === 'parked'): ?>
            <a href="<?= BASE_URL ?>/user/end-parking.php?id=<?= $booking['id'] ?>" class="btn btn-danger" onclick="return confirm('End parking session?');">
                <i class="bi bi-stop-circle"></i> End Parking
            </a>
            <?php elseif ($booking['status'] === 'pending'): ?>
            <a href="<?= BASE_URL ?>/user/cancel-booking.php?id=<?= $booking['id'] ?>" class="btn btn-secondary" onclick="return confirm('Cancel this booking?');">
                <i class="bi bi-trash"></i> Cancel Booking
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
