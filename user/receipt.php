<?php
/**
 * Show receipt for a booking (separate page)
 */
define('PARKING_ACCESS', true);
require_once dirname(__DIR__) . '/config/init.php';
requireLogin();
if (isAdmin()) { header('Location: ' . BASE_URL . '/index.php'); exit; }

$booking_id = (int) ($_GET['booking_id'] ?? 0);
$pdo = getDB();
$error = '';
$booking = null;
if ($booking_id) {
    $stmt = $pdo->prepare("SELECT b.id, b.entry_time, b.exit_time, b.planned_entry_time, b.booked_at, b.status AS booking_status,
        ps.slot_number, v.plate_number, v.model AS vehicle_model,
        p.amount, p.payment_method, p.payment_subtype, p.wallet_contact, p.account_number, p.payer_name, p.payment_status, p.paid_at
        FROM bookings b
        JOIN parking_slots ps ON ps.id = b.parking_slot_id
        LEFT JOIN payments p ON p.booking_id = b.id
        LEFT JOIN vehicles v ON v.id = b.vehicle_id
        WHERE b.id = ? AND b.user_id = ? LIMIT 1");
    $stmt->execute([$booking_id, currentUserId()]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$booking) {
        $error = 'Receipt not found or you do not have permission to view it.';
    }
} else {
    $error = 'Missing booking id.';
}

require dirname(__DIR__) . '/includes/header.php';
?>
<div class="container py-4">
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php else: ?>
        <?php
            $hourly_rate = 30.00;
            // prefer actual entry_time, otherwise planned_entry_time or booked_at for display
            $entry_source = $booking['entry_time'] ?? $booking['planned_entry_time'] ?? $booking['booked_at'] ?? null;
            $exit = $booking['exit_time'] ?? null;
            $hours = 0;
            $duration_mins = 0;
            if ($entry_source && $exit) {
                $duration_mins = max(0, (int) ((strtotime($exit) - strtotime($entry_source)) / 60));
                $hours = $duration_mins / 60;
            }
            // compute billed minutes (15-minute increments, minimum 15)
            $min_increment = 15;
            $billed_mins = $duration_mins > 0 ? max($min_increment, (int) (ceil($duration_mins / $min_increment) * $min_increment)) : 0;
            if (isset($booking['amount']) && $booking['amount'] !== null) {
                $subtotal = (float)$booking['amount'];
            } else {
                $subtotal = $billed_mins > 0 ? round(($billed_mins / 60) * $hourly_rate, 2) : 0.00;
            }
            $loyalty = 0.00; // placeholder
            $tax = round($subtotal * 0.12, 2);
            $total = round($subtotal - $loyalty + $tax, 2);
            if (!function_exists('slotLabel')) {
                function slotLabel($slot_number) {
                    if (preg_match('/^([A-Z])(\d+)$/', $slot_number, $m)) return $m[1] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT);
                    return $slot_number;
                }
            }
            $slot_label = slotLabel($booking['slot_number']);
        ?>

        <div class="receipt-wrap">
            <div class="receipt-card">
                <div class="receipt-header">
                    <h3>Payment Confirmed</h3>
                    <div style="opacity:0.95; margin-top:8px;">Your parking session has been completed successfully</div>
                </div>
                <div class="receipt-body">
                    <div class="receipt-section d-flex justify-content-between align-items-center">
                        <div>
                            <div style="font-size:0.95rem;color:#6b7280;">TRANSACTION ID</div>
                            <div style="font-weight:700;">PK-<?= str_pad((int)$booking['id'], 6, '0', STR_PAD_LEFT) ?></div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:0.95rem;color:#6b7280;">DATE & TIME</div>
                            <div style="font-weight:700;"><?= $booking['paid_at'] ? date('F j, Y \a\t g:i A', strtotime($booking['paid_at'])) : ($entry_source ? date('F j, Y \a\t g:i A', strtotime($entry_source)) : date('F j, Y \a\t g:i A')) ?></div>
                        </div>
                    </div>

                    <div class="receipt-section">
                        <h6 style="margin:0 0 8px 0;">Parking Details</h6>
                        <div class="d-flex justify-content-between"><div style="color:#6b7280;">Slot Location</div><div><strong><?= htmlspecialchars($slot_label) ?></strong></div></div>
                        <div class="d-flex justify-content-between"><div style="color:#6b7280;">Parking Duration</div><div><strong><?= $hours ? round($hours,2).'h' : '—' ?></strong></div></div>
                    </div>

                    <div class="receipt-section">
                        <h6 style="margin:0 0 8px 0;">Vehicle Information</h6>
                        <div class="d-flex justify-content-between"><div style="color:#6b7280;">Vehicle</div><div><strong><?= htmlspecialchars($booking['vehicle_model'] ?? '—') ?></strong></div></div>
                        <div class="d-flex justify-content-between"><div style="color:#6b7280;">License Plate</div><div><strong><?= htmlspecialchars($booking['plate_number'] ?? '—') ?></strong></div></div>
                    </div>

                    <div class="receipt-section">
                        <h6 style="margin:0 0 8px 0;">Pricing Breakdown</h6>
                        <div class="receipt-row"><div style="color:#6b7280;">Hourly Rate</div><div>&#8369;<?= number_format($hourly_rate,2) ?></div></div>
                        <div class="receipt-row"><div style="color:#6b7280;">Hours Parked</div><div><?= $hours ? round($hours,2) : '0.00' ?></div></div>
                        <div class="receipt-row"><div style="color:#6b7280;">Billed Minutes</div><div><?= $billed_mins ? htmlspecialchars($billed_mins) . ' min' : '—' ?></div></div>
                        <div class="receipt-row"><div style="color:#6b7280;">Subtotal</div><div>&#8369;<?= number_format($subtotal,2) ?></div></div>
                        <div class="receipt-row"><div style="color:#6b7280;">Loyalty Discount</div><div>&#8369;<?= number_format($loyalty,2) ?></div></div>
                        <div class="receipt-row"><div style="color:#6b7280;">Tax (12%)</div><div>&#8369;<?= number_format($tax,2) ?></div></div>
                        <div class="receipt-row" style="border-top:1px solid #eef2f7; padding-top:10px;"><div style="font-weight:700;">Total Amount</div><div class="receipt-amount">&#8369;<?= number_format($total,2) ?></div></div>
                    </div>

                    <div class="receipt-section">
                        <div style="color:#6b7280;">Payment Method</div>
                        <div style="font-size:0.85rem;color:#6b7280;margin-top:6px;">Rate: &#8369;<?= number_format($hourly_rate,2) ?>/hr — billed in 15-minute increments (minimum 15 minutes)</div>
                        <div style="margin-top:8px; display:flex; align-items:center; gap:12px;">
                            <div style="background:#ecfccb; padding:10px 12px; border-radius:8px; color:#065f46; font-weight:700;"><?= htmlspecialchars(ucwords(str_replace('_',' ',$booking['payment_method'] ?? '—'))) ?></div>
                            <div style="color:#6b7280;"><?= htmlspecialchars($booking['payment_subtype'] ?? ($booking['account_number'] ?? '—')) ?></div>
                        </div>
                    </div>

                    <div class="receipt-actions">
                        <a href="#" onclick="printReceipt();return false;" class="btn btn-outline-secondary">Print Receipt</a>
                        <a href="#" onclick="printReceipt();return false;" class="btn btn-outline-secondary">Download PDF</a>
                        <a href="<?= BASE_URL ?>/index.php" class="btn btn-success">Back to Dashboard</a>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<style>
.receipt-wrap { max-width:740px; margin:20px auto; display:flex; align-items:center; justify-content:center; min-height:60vh; }
.receipt-card { border-radius:12px; overflow:hidden; box-shadow:0 6px 18px rgba(16,24,40,0.08); }
.receipt-header { background:#10b981; color:white; padding:28px 32px; text-align:center; }
.receipt-header h3 { margin:0; font-size:1.35rem; }
.receipt-body { background:#f8fafc; padding:24px; }
.receipt-section { background:white; border-radius:8px; padding:16px; margin-bottom:12px; }
.receipt-row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px dashed #eef2f7; }
.receipt-row:last-child { border-bottom:0; }
.receipt-amount { font-size:1.25rem; font-weight:700; color:#059669; }
.receipt-actions { display:flex; gap:8px; justify-content:center; margin-top:12px; }
.receipt-actions .btn { padding:10px 18px; }

/* Print only the receipt content */
@media print {
    /* hide everything first */
    body * { visibility: hidden !important; }
    /* make receipt visible */
    .receipt-wrap, .receipt-wrap * { visibility: visible !important; }
    /* position receipt at the top-left */
    .receipt-wrap { position: absolute !important; top: 0; left: 0; width: 100%; }
    /* avoid page margins pushing content */
    @page { margin: 12mm; }
}
</style>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

<script>
function printReceipt() {
        // Use browser print dialog. @media print rules ensure only receipt prints.
        window.print();
}
</script>
