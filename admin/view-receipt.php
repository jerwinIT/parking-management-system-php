<?php
/**
 * Admin - View receipt for a booking (styled like ParkFlow receipt)
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
    SELECT b.id, b.entry_time, b.exit_time, b.booked_at, ps.slot_number, v.plate_number, v.model, v.color, u.full_name, p.amount, p.payment_status, p.payment_method
    FROM bookings b
    JOIN vehicles v ON b.vehicle_id = v.id
    JOIN parking_slots ps ON b.parking_slot_id = ps.id
    JOIN users u ON b.user_id = u.id
    LEFT JOIN payments p ON p.booking_id = b.id
    WHERE b.id = ?
");
$row->execute([$booking_id]);
$r = $row->fetch();

$page_title = 'View Receipt';
$current_page = 'admin-monitor';
require dirname(__DIR__) . '/includes/header.php';

if (!$r) {
    echo '<div class="receipt-page"><div class="receipt-card"><p class="text-muted mb-0">Booking not found.</p><a href="' . BASE_URL . '/admin/monitor.php" class="btn btn-outline-secondary mt-3">Back to Monitor</a></div></div>';
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

// Use only booked/completed data from DB — never current time
$entry_ts = $r['entry_time'] ? strtotime($r['entry_time']) : strtotime($r['booked_at']);
$exit_ts = $r['exit_time'] ? strtotime($r['exit_time']) : $entry_ts;
$duration_mins = (int) (($exit_ts - $entry_ts) / 60);
$duration_hours = round($duration_mins / 60, 2);
$hourly_rate = 30;
// bill in 15-minute increments (minimum 15 mins)
$min_increment = 15;
$billed_mins = max($min_increment, (int) (ceil($duration_mins / $min_increment) * $min_increment));
$subtotal = round(($billed_mins / 60) * $hourly_rate, 2);
$stored_amount = (float) ($r['amount'] ?? 0);
$total_amount = $stored_amount > 0 ? $stored_amount : $subtotal;
$trans_id = 'PK-' . date('Y') . '-' . str_pad($r['id'], 5, '0', STR_PAD_LEFT);
$time_range = date('g:i A', $entry_ts) . ' - ' . date('g:i A', $exit_ts);
$duration_str = $duration_mins >= 60 ? floor($duration_mins/60) . 'h ' . ($duration_mins%60) . 'm' : $duration_mins . 'm';
$payment_method = !empty($r['payment_method']) ? $r['payment_method'] : 'Cash / To be paid';
$payment_status = $r['payment_status'] ?? 'pending';
?>

<style>
.receipt-page { max-width: 560px; margin: 3rem auto 3rem; padding: 0 1rem; }
.receipt-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,.08); overflow: hidden; }
.receipt-header { background: #22c55e; color: #fff; padding: 1.75rem 1.5rem; text-align: center; }
.receipt-header .receipt-icon { width: 56px; height: 56px; background: rgba(255,255,255,.25); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 0.75rem; font-size: 1.75rem; }
.receipt-header h1 { font-size: 1.25rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 0.35rem; }
.receipt-header p { font-size: 0.875rem; opacity: .95; margin: 0; }
.receipt-body { padding: 1.5rem; }
.receipt-section { margin-bottom: 1.5rem; }
.receipt-section:last-child { margin-bottom: 0; }
.receipt-section-title { font-size: 0.95rem; font-weight: 700; color: #374151; margin-bottom: 1rem; }
.receipt-row { display: flex; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem; }
.receipt-row:last-child { margin-bottom: 0; }
.receipt-col { flex: 1; }
.receipt-label { font-size: 0.65rem; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem; }
.receipt-value { font-size: 1rem; font-weight: 700; color: #374151; }
.receipt-value-sm { font-size: 0.85rem; font-weight: 400; color: #6b7280; margin-top: 0.15rem; }
.receipt-divider { height: 1px; background: #e5e7eb; margin: 1.25rem 0; }
.receipt-details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
.receipt-detail-item { display: flex; gap: 0.75rem; align-items: flex-start; }
.receipt-detail-icon { width: 40px; height: 40px; min-width: 40px; background: #dcfce7; color: #16a34a; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
.receipt-detail-content .receipt-value-sm { margin-top: 0.1rem; }
.receipt-bg-green { background: #f0fdf4; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.25rem; }
.receipt-bg-green .receipt-section-title { color: #166534; margin-bottom: 0.75rem; }
.receipt-pricing-row { display: flex; justify-content: space-between; align-items: center; padding: 0.4rem 0; font-size: 0.9rem; }
.receipt-pricing-row .label { color: #6b7280; }
.receipt-pricing-row .value { font-weight: 600; color: #374151; }
.receipt-total { display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; padding-top: 1rem; border-top: 2px solid #e5e7eb; }
.receipt-total .label { font-size: 1rem; font-weight: 700; color: #374151; }
.receipt-total .value { font-size: 1.35rem; font-weight: 700; color: #16a34a; }
.receipt-payment-row { display: flex; gap: 1rem; align-items: flex-start; }
.receipt-footer { text-align: center; padding: 1.5rem; color: #6b7280; font-size: 0.9rem; border-top: 1px solid #e5e7eb; }
.receipt-footer p { margin: 0.25rem 0; }
.receipt-footer .receipt-gen { font-size: 0.75rem; color: #9ca3af; margin-top: 0.75rem; }
.receipt-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.75rem; padding: 1.25rem 1.5rem; background: #f9fafb; border-top: 1px solid #e5e7eb; }
.receipt-btn { padding: 0.6rem 1.25rem; border-radius: 8px; font-weight: 600; font-size: 0.9rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; border: none; cursor: pointer; }
.receipt-btn-print { background: #16a34a; color: #fff; }
.receipt-btn-print:hover { background: #15803d; color: #fff; }
.receipt-btn-outline { background: #fff; color: #374151; border: 1px solid #d1d5db; }
.receipt-btn-outline:hover { background: #f3f4f6; color: #111; }
</style>

<div class="receipt-page">
    <div class="receipt-card">
        <div class="receipt-header">
            <div class="receipt-icon"><i class="bi bi-check-lg"></i></div>
            <h1>Payment Confirmed</h1>
            <p>Your parking session has been completed successfully.</p>
        </div>

        <div class="receipt-body">
            <div class="receipt-section receipt-row">
                <div class="receipt-col">
                    <div class="receipt-label">Transaction ID</div>
                    <div class="receipt-value"><?= htmlspecialchars($trans_id) ?></div>
                </div>
                <div class="receipt-col">
                    <div class="receipt-label">Date &amp; Time</div>
                    <div class="receipt-value"><?= date('F j, Y', $entry_ts) ?></div>
                    <div class="receipt-value-sm"><?= date('g:i A', $entry_ts) ?></div>
                </div>
            </div>
            <div class="receipt-divider"></div>

            <div class="receipt-section">
                <div class="receipt-section-title">Parking Details</div>
                <div class="receipt-details-grid">
                    <div class="receipt-detail-item">
                        <div class="receipt-detail-icon"><i class="bi bi-geo-alt-fill"></i></div>
                        <div class="receipt-detail-content">
                            <div class="receipt-label">Slot Location</div>
                            <div class="receipt-value"><?= htmlspecialchars($r['slot_number']) ?></div>
                            <div class="receipt-value-sm">Parking Lot</div>
                        </div>
                    </div>
                    <div class="receipt-detail-item">
                        <div class="receipt-detail-icon"><i class="bi bi-clock-fill"></i></div>
                        <div class="receipt-detail-content">
                            <div class="receipt-label">Parking Duration</div>
                            <div class="receipt-value"><?= htmlspecialchars($duration_str) ?></div>
                            <div class="receipt-value-sm"><?= htmlspecialchars($time_range) ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="receipt-divider"></div>

            <div class="receipt-bg-green">
                <div class="receipt-section-title">Vehicle Information</div>
                <div class="receipt-row">
                    <div class="receipt-col">
                        <div class="receipt-label">Vehicle</div>
                        <div class="receipt-value" style="font-weight:600;"><?= htmlspecialchars($r['model'] ?? '—') ?></div>
                    </div>
                    <div class="receipt-col">
                        <div class="receipt-label">License Plate</div>
                        <div class="receipt-value" style="font-weight:600;"><?= htmlspecialchars($r['plate_number']) ?></div>
                    </div>
                </div>
            </div>

            <div class="receipt-section">
                <div class="receipt-section-title">Pricing Breakdown</div>
                <div class="receipt-pricing-row">
                    <span class="label">Hourly Rate</span>
                    <span class="value">&#8369;<?= number_format($hourly_rate, 2) ?></span>
                </div>
                <div class="receipt-pricing-row">
                    <span class="label">Hours Parked</span>
                    <span class="value"><?= number_format($duration_hours, 2) ?>h</span>
                </div>
                <div class="receipt-pricing-row">
                    <span class="label">Subtotal</span>
                    <span class="value">&#8369;<?= number_format($subtotal, 2) ?></span>
                </div>
                <div class="receipt-total">
                    <span class="label">Total Amount</span>
                    <span class="value">&#8369;<?= number_format($total_amount, 2) ?></span>
                </div>
            </div>
            <div class="receipt-divider"></div>

            <div class="receipt-bg-green receipt-payment-row">
                <div class="receipt-detail-icon"><i class="bi bi-credit-card-fill"></i></div>
                <div>
                    <div class="receipt-label">Payment Method</div>
                    <div class="receipt-value" style="font-size:0.95rem; font-weight:600;"><?= htmlspecialchars($payment_method) ?></div>
                    <div class="receipt-value-sm"><?= ucfirst($payment_status) ?></div>
                </div>
            </div>
        </div>

        <div class="receipt-footer">
            <p><strong>Thank you for using ParkIt</strong></p>
            <p>For support, contact your administrator.</p>
            <p class="receipt-gen">Receipt generated on <?= date('F j, Y \a\t g:i A', time()) ?></p>
        </div>

        <div class="receipt-actions">
            <button type="button" class="receipt-btn receipt-btn-print" onclick="window.print();"><i class="bi bi-printer-fill"></i> Print Receipt</button>
            <button type="button" class="receipt-btn receipt-btn-outline" onclick="window.print();"><i class="bi bi-download"></i> Download PDF</button>
            <a href="<?= BASE_URL ?>/admin/monitor.php" class="receipt-btn receipt-btn-outline"><i class="bi bi-grid-3x3-gap"></i> Back to Monitor</a>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
