<?php
/**
 * Admin - Monitor all vehicles inside the parking area (current parked)
 */
define('PARKING_ACCESS', true);
require_once dirname(__DIR__) . '/config/init.php';
requireAdmin();

$page_title = 'Monitor Vehicles';
$current_page = 'admin-monitor';

$pdo = getDB();

// Auto-end parked bookings whose planned duration has elapsed since actual entry
try {
    // Find parked bookings where (entry_time + planned_duration) <= NOW()
    $sel = $pdo->prepare("SELECT b.id, b.parking_slot_id, TIMESTAMPDIFF(MINUTE, b.planned_entry_time, b.exit_time) AS planned_mins
        FROM bookings b
        WHERE b.status = 'parked' AND b.entry_time IS NOT NULL AND b.planned_entry_time IS NOT NULL
        AND DATE_ADD(b.entry_time, INTERVAL TIMESTAMPDIFF(MINUTE, b.planned_entry_time, b.exit_time) MINUTE) <= NOW()");
    $sel->execute();
    $toEnd = $sel->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($toEnd)) {
        $pdo->beginTransaction();
        $uBooking = $pdo->prepare("UPDATE bookings SET status = 'completed', exit_time = DATE_ADD(entry_time, INTERVAL ? MINUTE) WHERE id = ? AND status = 'parked'");
        $uSlot = $pdo->prepare("UPDATE parking_slots SET status = 'available' WHERE id = ?");
        foreach ($toEnd as $r) {
            $mins = max(0, (int) ($r['planned_mins'] ?? 0));
            if ($mins <= 0) continue;
            $uBooking->execute([$mins, $r['id']]);
            $uSlot->execute([$r['parking_slot_id']]);
        }
        $pdo->commit();
    }
} catch (Exception $e) {
    try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Exception $_) {}
    // silently ignore auto-end errors to avoid breaking monitor UI
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $booking_id = (int) ($_POST['booking_id'] ?? 0);
    
    if ($action === 'mark_parked' && $booking_id) {
        // Mark booking as parked and set actual entry_time to now
        $stmt = $pdo->prepare('SELECT status, parking_slot_id FROM bookings WHERE id = ?');
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch();
        
        if ($booking && $booking['status'] === 'pending') {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE bookings SET status = ?, entry_time = NOW() WHERE id = ?')->execute(['parked', $booking_id]);
                $pdo->prepare('UPDATE parking_slots SET status = ? WHERE id = ?')->execute(['occupied', $booking['parking_slot_id']]);
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Vehicle marked as parked']);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Booking not found or already parked']);
        }
    } elseif ($action === 'end_parking' && $booking_id) {
        // Mark booking as completed and free slot
        $stmt = $pdo->prepare('SELECT parking_slot_id FROM bookings WHERE id = ? AND status = ?');
        $stmt->execute([$booking_id, 'parked']);
        $row = $stmt->fetch();
        
        if ($row) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE bookings SET status = ?, exit_time = NOW() WHERE id = ?')->execute(['completed', $booking_id]);
                $pdo->prepare('UPDATE parking_slots SET status = ? WHERE id = ?')->execute(['available', $row['parking_slot_id']]);
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Vehicle parking ended']);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Booking not found']);
        }
    }
    exit;
}

// Get filter from URL
$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'parked', 'pending'])) {
    $filter = 'all';
}

// REMOVED: Auto-end logic that was causing parked vehicles to end prematurely
// The original logic compared total parking duration to operating hours,
// which incorrectly ended overnight or long-term parking sessions.
// 
// If you need auto-end functionality, consider:
// 1. Adding a 'planned_exit_time' or 'duration_hours' column to bookings table
// 2. Implementing a scheduled task (cron job) instead of running on page load
// 3. Manually ending parking sessions from the monitor interface

// Build query based on filter
$where_clause = '';
if ($filter === 'parked') {
    $where_clause = "WHERE b.status = 'parked'";
} elseif ($filter === 'pending') {
    $where_clause = "WHERE b.status = 'pending'";
} else {
    // all = show only active bookings (pending and parked, exclude completed/cancelled)
    $where_clause = "WHERE b.status IN ('pending', 'parked')";
}

$vehicles = $pdo->query("
    SELECT b.id AS booking_id, b.status AS booking_status, b.booked_at, b.entry_time, b.planned_entry_time, b.exit_time,
           u.full_name, u.phone, v.plate_number, v.model, v.color, ps.slot_number,
           IF(b.planned_entry_time IS NOT NULL AND b.exit_time IS NOT NULL, TIMESTAMPDIFF(MINUTE, b.planned_entry_time, b.exit_time), 0) AS planned_duration_minutes
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN vehicles v ON b.vehicle_id = v.id
    JOIN parking_slots ps ON b.parking_slot_id = ps.id
    {$where_clause}
    ORDER BY b.entry_time DESC, b.booked_at DESC
")->fetchAll();

$total_slots = (int) $pdo->query("SELECT COUNT(*) FROM parking_slots")->fetchColumn();
$occupied_slots = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status IN ('parked', 'pending')")->fetchColumn();
$available_slots = (int) $pdo->query("SELECT COUNT(*) FROM parking_slots WHERE status = 'available'")->fetchColumn();
$maintenance_slots = (int) $pdo->query("SELECT COUNT(*) FROM parking_slots WHERE status = 'maintenance'")->fetchColumn();

require dirname(__DIR__) . '/includes/header.php';
?>

<style>
    .monitor-page {
        width: 100%;
        max-width: 100%;
        margin: 0;
        padding: 0;
    }

    .monitor-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 2rem;
        margin-top: 3rem;
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        padding: 2rem;
        border-radius: 16px;
        color: #fff;
        box-shadow: 0 8px 24px rgba(22, 163, 74, 0.15);
    }

    .monitor-header h3 {
        font-weight: 800;
        color: #fff;
        font-size: 1.75rem;
        margin-bottom: 0.5rem;
        letter-spacing: -0.5px;
    }

    .monitor-header .text-muted {
        font-size: 0.95rem;
        color: rgba(255, 255, 255, 0.95);
    }

    .stats-grid {
        display: flex;
        gap: 1.5rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }

    @media (max-width: 1400px) {
        .stats-grid {
            display: flex;
        }
    }

    @media (max-width: 1024px) {
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 640px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
    }

    .stat-card {
        flex: 1;
        min-width: 200px;
        background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
        border-radius: 14px;
        padding: 1.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        border: 1px solid #e5e7eb;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    }

    .stat-card.available {
        background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
        border-color: #86efac;
    }

    .stat-card.available .stat-label {
        color: #166534;
    }

    .stat-card.available .stat-number {
        color: #16a34a;
    }

    .stat-card.occupied {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        border-color: #fca5a5;
    }

    .stat-card.occupied .stat-label {
        color: #7f1d1d;
    }

    .stat-card.occupied .stat-number {
        color: #dc2626;
    }

    .stat-card.maintenance {
        background: linear-gradient(135deg, #fef08a 0%, #fde047 100%);
        border-color: #fde047;
    }

    .stat-card.maintenance .stat-label {
        color: #854d0e;
    }

    .stat-card.maintenance .stat-number {
        color: #ca8a04;
    }

    .stat-card.total {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        border-color: #93c5fd;
    }

    .stat-card.total .stat-label {
        color: #0c4a6e;
    }

    .stat-card.total .stat-number {
        color: #2563eb;
    }

    .stat-label {
        font-size: 0.75rem;
        color: #9ca3af;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 800;
        letter-spacing: -1px;
    }

    .stat-meta {
        font-size: 0.85rem;
        color: #6b7280;
        margin-top: 0.5rem;
    }

    @media (max-width: 768px) {
        .monitor-refresh-btn {
            position: static;
            display: inline-block;
            margin-bottom: 1rem;
        }
        .monitor-header {
            padding: 1.5rem;
        }
        .monitor-header h3 {
            font-size: 1.5rem;
        }
    }

    .vehicle-cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 1.75rem;
        margin-top: 2.5rem;
    }

    @media (max-width: 768px) {
        .vehicle-cards-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
    }

    .vehicle-card {
        background: linear-gradient(135deg, #fff 0%, #f9fafb 100%);
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.05);
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        padding: 1.5rem;
    }

    .vehicle-card:hover {
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12), 0 2px 4px rgba(0, 0, 0, 0.08);
        transform: translateY(-4px);
        border-color: #d1d5db;
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .vehicle-card:hover {
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12), 0 2px 4px rgba(0, 0, 0, 0.08);
        border-color: #d1d5db;
        transform: translateY(-4px);
    }

    .card-header-top {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        padding: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin: -1.5rem -1.5rem 1.5rem -1.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .vehicle-title {
        font-size: 1.15rem;
        font-weight: 700;
        color: #fff;
    }

    .vehicle-plate {
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.8);
        margin-top: 0.35rem;
        letter-spacing: 0.5px;
    }

    .status-badge {
        display: inline-block;
        padding: 0.6rem 1.2rem;
        border-radius: 999px;
        font-size: 0.8rem;
        font-weight: 700;
        white-space: nowrap;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
    }

    .status-badge.parked {
        background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
        color: #15803d;
        box-shadow: 0 3px 12px rgba(22, 163, 74, 0.2);
    }

    .status-badge.not-yet {
        background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%);
        color: #92400e;
        box-shadow: 0 3px 12px rgba(245, 158, 11, 0.2);
    }

    .card-content {
        margin-bottom: 1.75rem;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 0.9rem;
        font-size: 0.9rem;
        padding: 0.6rem;
        background: #f9fafb;
        border-radius: 8px;
        border-left: 3px solid #16a34a;
    }

    .info-row:last-child {
        margin-bottom: 0;
    }

    .info-label {
        color: #9ca3af;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
    }

    .info-value {
        color: #374151;
        font-weight: 700;
    }

    .location-badge {
        background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
        color: #15803d;
        padding: 0.5rem 1rem;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.95rem;
        box-shadow: 0 2px 8px rgba(22, 163, 74, 0.15);
    }

    .time-value {
        color: #374151;
        font-weight: 700;
    }

    .duration-value {
        color: #16a34a;
        font-weight: 700;
        font-size: 1rem;
    }

    .card-actions {
        display: flex;
        gap: 0.75rem;
        padding: 1rem;
        background: #f0fdf4;
        border-radius: 12px;
        border-top: 1px solid #dcfce7;
        margin: 0 -1.5rem -1.5rem -1.5rem;
    }

    .card-actions a,
    .card-actions button {
        flex: 1;
        padding: 0.75rem 1rem;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.85rem;
        border: none;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    .btn-view {
        background: #f3f4f6;
        color: #374151;
        border: 1px solid #e5e7eb;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    }

    .btn-view:hover {
        background: #e5e7eb;
        color: #111;
        border-color: #d1d5db;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transform: translateY(-2px);
    }

    .btn-parked {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        color: #fff;
        box-shadow: 0 4px 14px rgba(22, 163, 74, 0.3);
    }

    .btn-parked:hover {
        background: linear-gradient(135deg, #15803d 0%, #166d31 100%);
        box-shadow: 0 8px 20px rgba(22, 163, 74, 0.4);
        transform: translateY(-3px);
    }

    .btn-end {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: #fff;
        box-shadow: 0 4px 14px rgba(239, 68, 68, 0.3);
    }

    .btn-end:hover {
        background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4);
        transform: translateY(-3px);
    }

    .filter-tabs {
        display: flex;
        gap: 1rem;
        margin-bottom: 2.5rem;
        flex-wrap: wrap;
        padding: 1.25rem;
        background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
        border-radius: 14px;
        border: 1px solid #e5e7eb;
    }

    .filter-tab {
        padding: 0.75rem 1.5rem;
        border-radius: 10px;
        border: 2px solid transparent;
        background: #fff;
        color: #6b7280;
        font-weight: 700;
        font-size: 0.9rem;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
    }

    .filter-tab:hover {
        border-color: #16a34a;
        color: #16a34a;
        background: #fff;
        box-shadow: 0 4px 12px rgba(22, 163, 74, 0.15);
        transform: translateY(-2px);
    }

    .filter-tab.active {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        color: #fff;
        border-color: #16a34a;
        box-shadow: 0 6px 16px rgba(22, 163, 74, 0.3);
    }

    .filter-tab.active {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        color: #fff;
        border-color: #16a34a;
        box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
    }
</style>


    <div class="monitor-header">
        <div>
            <h3>Monitor Vehicles</h3>
            <p >Real-time parking lot monitoring and vehicle tracking</p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>/admin/monitor.php" class="btn btn-sm" style="background: rgba(255, 255, 255, 0.2); border: 1px solid rgba(255, 255, 255, 0.3); color: #fff; font-weight: 700;" title="Refresh list"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card available">
            <div class="stat-label">Available Slots</div>
            <div class="stat-number"><?= $available_slots ?></div>
        </div>
        <div class="stat-card occupied">
            <div class="stat-label">Occupied Slots</div>
            <div class="stat-number"><?= $occupied_slots ?></div>
        </div>
        <div class="stat-card total">
            <div class="stat-label">Total Slots</div>
            <div class="stat-number"><?= $total_slots ?></div>
        </div>
    </div>

    <div class="filter-tabs">
        <a href="<?= BASE_URL ?>/admin/monitor.php?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
            All Vehicles
        </a>
        <a href="<?= BASE_URL ?>/admin/monitor.php?filter=parked" class="filter-tab <?= $filter === 'parked' ? 'active' : '' ?>">
            Currently Parked
        </a>
        <a href="<?= BASE_URL ?>/admin/monitor.php?filter=pending" class="filter-tab <?= $filter === 'pending' ? 'active' : '' ?>">
            Not Yet Parked
        </a>
    </div>
    
    <?php if (empty($vehicles)): ?>
        <div style="background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); border: 2px dashed #d1d5db; border-radius: 14px; padding: 3rem 2rem; text-align: center; color: #6b7280;">
            <i class="bi bi-car-front" style="font-size: 3.5rem; display: block; margin-bottom: 1rem; color: #d1d5db;"></i>
            <p style="font-size: 1.1rem; font-weight: 600; margin: 1rem 0 0.5rem 0; color: #374151;">No vehicles currently parked</p>
            <p style="font-size: 0.95rem; margin: 0; color: #9ca3af;">All parking slots are available or waiting for bookings</p>
        </div>
    <?php else: ?>
        <div class="vehicle-cards-grid">
        <?php foreach ($vehicles as $v):
            $is_parked = $v['booking_status'] === 'parked';
            // Show planned duration from booking form (planned_entry to exit)
            $planned_mins = (int) ($v['planned_duration_minutes'] ?? 0);
            if ($planned_mins > 0) {
                $hours = floor($planned_mins / 60);
                $mins = $planned_mins % 60;
                $duration = $hours > 0 ? "{$hours}h {$mins}m" : "{$mins}m";
            } else {
                $duration = "-";
            }
            $time_in = $is_parked && $v['entry_time'] ? date('g:i A', strtotime($v['entry_time'])) : date('g:i A', strtotime($v['booked_at']));
            $time_label = $is_parked ? 'Time In' : 'Booked At';
        ?>
            <div class="vehicle-card" data-booking-id="<?= $v['booking_id'] ?>">
                <div class="card-header-top">
                    <div style="flex: 1;">
                        <div class="vehicle-title"><?= htmlspecialchars($v['model'] ?? 'Unknown') ?></div>
                        <div class="vehicle-plate"><?= htmlspecialchars($v['plate_number']) ?></div>
                    </div>
                        <span class="status-badge status-badge-<?= $v['booking_id'] ?> <?= $is_parked ? 'parked' : 'not-yet' ?>">
                            <?= $is_parked ? 'Parked' : 'Not Yet Parked' ?>
                    </span>
                </div>

                <div class="card-content">
                    <div class="info-row">
                        <span class="info-label">Owner:</span>
                        <span class="info-value"><?= htmlspecialchars($v['full_name'] ?? '-') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Color:</span>
                        <span class="info-value"><?= htmlspecialchars($v['color'] ?? '-') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Location:</span>
                        <span class="info-value location-badge"><?= htmlspecialchars($v['slot_number']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Time In</span>
                        <span class="info-value time-value time-value-<?= $v['booking_id'] ?>"><?= $time_in ?></span>
                    </div>
                    <?php if ($is_parked): ?>
                    <div class="info-row">
                        <span class="info-label">Duration</span>
                        <span class="info-value duration-value duration-value-<?= $v['booking_id'] ?>"><?= htmlspecialchars($duration) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="card-actions">
                    <a href="#" class="btn-view" onclick="showVehicleModal(<?= $v['booking_id'] ?>); return false;">
                        <i class="bi bi-eye"></i> View
                    </a>
                    <?php if (!$is_parked): ?>
                            <button type="button" class="btn-parked btn-mark-park" data-booking-id="<?= $v['booking_id'] ?>" onclick="handleMarkParked(event, <?= $v['booking_id'] ?>)">
                                <i class="bi bi-check-circle-fill"></i> Parked
                    </button>
                    <?php else: ?>
                            <a href="#" class="btn-end btn-end-parking" data-booking-id="<?= $v['booking_id'] ?>" onclick="handleEndParking(event, <?= $v['booking_id'] ?>);">
                                <i class="bi bi-x-circle-fill"></i> End
                            </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>


<script>
function handleMarkParked(e, bookingId) {
    e.preventDefault();
    if (!confirm('Mark vehicle as parked?')) return;
    
    const formData = new FormData();
    formData.append('action', 'mark_parked');
    formData.append('booking_id', bookingId);
    
    fetch('<?= BASE_URL ?>/admin/monitor.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const card = document.querySelector(`[data-booking-id="${bookingId}"]`);
            if (card) {
                // Update status badge
                const badgeEl = card.querySelector(`.status-badge-${bookingId}`);
                if (badgeEl) {
                    badgeEl.textContent = 'Parked';
                    badgeEl.classList.remove('not-yet');
                    badgeEl.classList.add('parked');
                }
                
                // Update time in
                const timeEl = card.querySelector(`.time-value-${bookingId}`);
                if (timeEl) {
                    const now = new Date();
                    timeEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
                }
                
                // Replace Parked button with End button
                const actionsDiv = card.querySelector('.card-actions');
                const parkedBtn = actionsDiv.querySelector('.btn-mark-park');
                const endBtn = document.createElement('a');
                endBtn.href = '#';
                endBtn.className = 'btn-end btn-end-parking';
                endBtn.setAttribute('data-booking-id', bookingId);
                endBtn.innerHTML = '<i class="bi bi-x-circle-fill"></i> End';
                endBtn.onclick = (e) => handleEndParking(e, bookingId);
                if (parkedBtn) {
                    parkedBtn.replaceWith(endBtn);
                } else {
                    actionsDiv.appendChild(endBtn);
                }
                
                // Fetch planned duration from server and add duration row
                fetch('<?= BASE_URL ?>/admin/get-vehicle-details.php?booking_id=' + bookingId)
                    .then(r => r.json())
                    .then(res => {
                        if (res.success && res.data) {
                            const d = res.data;
                            const mins = parseInt(d.duration_minutes || 0, 10);
                            const h = Math.floor(mins / 60);
                            const m = mins % 60;
                            const durationText = h > 0 ? `${h}h ${m}m` : `${m}m`;
                            
                            // Check if duration row already exists
                            let durationRow = card.querySelector('.info-row:has(.duration-value-' + bookingId + ')');
                            if (!durationRow) {
                                durationRow = document.createElement('div');
                                durationRow.className = 'info-row';
                                durationRow.innerHTML = `<span class="info-label">Duration</span>`;
                                const durationValue = document.createElement('span');
                                durationValue.className = 'info-value duration-value duration-value-' + bookingId;
                                durationValue.textContent = durationText;
                                durationRow.appendChild(durationValue);
                                card.querySelector('.card-content').appendChild(durationRow);
                            }
                        }
                    })
                    .catch(err => {});
            }
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => alert('Error: ' + err.message));
}

function handleEndParking(e, bookingId) {
    e.preventDefault();
    if (!confirm('End parking? Vehicle will be removed from the list.')) return;
    
    const formData = new FormData();
    formData.append('action', 'end_parking');
    formData.append('booking_id', bookingId);
    
    fetch('<?= BASE_URL ?>/admin/monitor.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const card = document.querySelector(`[data-booking-id="${bookingId}"]`);
            if (card) {
                card.style.animation = 'fadeOut 0.3s ease-out';
                setTimeout(() => card.remove(), 300);
            }
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => alert('Error: ' + err.message));
}
</script>

<style>
@keyframes fadeOut {
    from {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
    to {
        opacity: 0;
        transform: translateY(-10px) scale(0.95);
    }
}
</style>
<!-- Vehicle details modal -->
<div id="vehicleModal" class="vm-modal" style="display:none;">
    <div class="vm-overlay" onclick="closeVehicleModal()"></div>
    <div class="vm-dialog" role="dialog" aria-hidden="true">
        <div class="vm-header">
            <div class="vm-title">Vehicle Details</div>
            <button class="vm-close" onclick="closeVehicleModal()">&times;</button>
        </div>
        <div class="vm-body">
            <div class="vm-row vm-top">
                <div>
                    <div class="vm-label">MODEL</div>
                    <div class="vm-model vm-value">—</div>
                </div>
                <div>
                    <div class="vm-label">PLATE NUMBER</div>
                    <div class="vm-plate vm-value text-success">—</div>
                </div>
            </div>

            <div class="vm-row">
                <div>
                    <div class="vm-label">COLOR</div>
                    <div class="vm-value vm-color">—</div>
                </div>
                <div>
                    <div class="vm-label">STATUS</div>
                    <div class="vm-status vm-badge">—</div>
                </div>
            </div>

            <hr />

            <div class="vm-row">
                <div>
                    <div class="vm-label">OWNER</div>
                    <div class="vm-value vm-owner">—</div>
                </div>
                <div>
                    <div class="vm-label">LOCATION</div>
                    <div class="vm-value vm-location text-success">—</div>
                </div>
            </div>

            <div class="vm-row">
                <div>
                    <div class="vm-label">TIME IN</div>
                    <div class="vm-value vm-time">—</div>
                </div>
                <div id="durationContainer">
                    <div class="vm-label">DURATION</div>
                    <div class="vm-value vm-duration text-success">—</div>
                </div>
            </div>
        </div>
        <div class="vm-footer">
            <button class="btn btn-outline-secondary" onclick="closeVehicleModal()">Close</button>
            <a href="#" class="btn btn-success vm-contact">Contact Owner</a>
        </div>
    </div>
</div>

<style>
/* Modal styles matching requested design */
.vm-modal {
    position: fixed;
    inset: 0;
    z-index: 1200;
    display: flex;
    align-items: center;
    justify-content: center;
}

.vm-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    backdrop-filter: blur(3px);
}

.vm-dialog {
    position: relative;
    width: 540px;
    max-width: 95%;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);
    overflow: hidden;
    animation: slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.vm-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem 1.75rem;
    border-bottom: 2px solid #f3f4f6;
    background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
}

.vm-title {
    font-weight: 800;
    font-size: 1.3rem;
    color: #111;
    letter-spacing: -0.5px;
}

.vm-close {
    background: none;
    border: none;
    font-size: 1.75rem;
    line-height: 1;
    color: #9ca3af;
    cursor: pointer;
    transition: all 0.2s ease;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
}

.vm-close:hover {
    color: #111;
    background: #e5e7eb;
    transform: rotate(90deg);
}

.vm-body {
    padding: 1.75rem;
    color: #374151;
}

.vm-row {
    display: flex;
    justify-content: space-between;
    gap: 1.5rem;
    margin-bottom: 1.25rem;
}

.vm-row.vm-top {
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f3f4f6;
}

.vm-row > div {
    flex: 1;
}

.vm-label {
    font-size: 0.7rem;
    color: #9ca3af;
    font-weight: 700;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    margin-bottom: 0.5rem;
}

.vm-value {
    font-size: 1rem;
    font-weight: 700;
    color: #374151;
}

.vm-model {
    font-size: 1.1rem;
    color: #111;
}

.vm-plate {
    color: #16a34a;
    font-weight: 800;
    font-size: 1.05rem;
    letter-spacing: 1px;
}

.vm-badge {
    display: inline-block;
    padding: 0.5rem 0.85rem;
    border-radius: 999px;
    background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%);
    color: #92400e;
    font-weight: 700;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.vm-footer {
    display: flex;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 1.5rem 1.75rem;
    border-top: 2px solid #f3f4f6;
    background: #f9fafb;
}

.vm-footer .btn {
    flex: 1;
    padding: 0.75rem;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.vm-footer .btn-outline-secondary {
    background: #fff;
    color: #374151;
    border: 1px solid #d1d5db;
}

.vm-footer .btn-outline-secondary:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
    color: #111;
}

.vm-contact {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%) !important;
    color: #fff !important;
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
    border: none;
}

.vm-contact:hover {
    background: linear-gradient(135deg, #15803d 0%, #166d31 100%) !important;
    box-shadow: 0 6px 16px rgba(22, 163, 74, 0.4);
    transform: translateY(-2px);
}
</style>

<script>
function showVehicleModal(bookingId) {
    const modal = document.getElementById('vehicleModal');
    // reset
    modal.querySelector('.vm-model').textContent = '—';
    modal.querySelector('.vm-plate').textContent = '—';
    modal.querySelector('.vm-color').textContent = '—';
    modal.querySelector('.vm-status').textContent = '—';
    modal.querySelector('.vm-owner').textContent = '—';
    modal.querySelector('.vm-location').textContent = '—';
    modal.querySelector('.vm-time').textContent = '—';
    modal.querySelector('.vm-duration').textContent = '—';
    modal.querySelector('.vm-contact').href = '#';
    modal.style.display = 'flex';

    fetch('<?= BASE_URL ?>/admin/get-vehicle-details.php?booking_id=' + bookingId)
        .then(r => r.json())
        .then(res => {
            if (!res.success) return alert('Error: ' + (res.message || 'Failed to load'));
            const d = res.data;
            modal.querySelector('.vm-model').textContent = d.model || '-';
            modal.querySelector('.vm-plate').textContent = d.plate_number || '-';
            modal.querySelector('.vm-color').textContent = d.color || '-';
            modal.querySelector('.vm-owner').textContent = d.owner || '-';
            modal.querySelector('.vm-location').textContent = d.slot_number || '-';
            modal.querySelector('.vm-time').textContent = d.entry_time || '-';
            // duration formatting - show planned duration (static, not live)
            const durationContainer = modal.querySelector('#durationContainer');
            if (d.status === 'parked' && d.duration_minutes > 0) {
                const mins = Math.max(0, parseInt(d.duration_minutes || 0, 10));
                const h = Math.floor(mins / 60);
                const m = mins % 60;
                const durationText = h > 0 ? `${h}h ${m}m` : `${m}m`;
                durationContainer.style.display = '';
                modal.querySelector('.vm-duration').textContent = durationText;
            } else {
                durationContainer.style.display = 'none';
            }
            // status badge
            const statusEl = modal.querySelector('.vm-status');
            statusEl.textContent = d.status ? (d.status === 'parked' ? 'Parked' : (d.status === 'pending' ? 'Not Yet Parked' : d.status)) : '-';
            // phone link
            if (d.phone) {
                modal.querySelector('.vm-contact').href = 'tel:' + d.phone;
            } else {
                modal.querySelector('.vm-contact').href = '#';
            }
        })
        .catch(err => alert('Error: ' + err.message));
}

function closeVehicleModal() {
    const modal = document.getElementById('vehicleModal');
    modal.style.display = 'none';
}
</script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>