<?php
/**
 * Home: Landing page (guests) or Dashboard (logged-in users)
 */
define('PARKING_ACCESS', true);
require_once __DIR__ . '/config/init.php';

if (!isLoggedIn()) {
    require __DIR__ . '/landing.php';
    exit;
}

$page_title = isAdmin() ? 'Admin Dashboard' : 'User Dashboard';
$current_page = 'dashboard';

// Get parking stats (from settings and slots)
$pdo = getDB();
// load settings safely — fetch may return false if table is empty
$settingsRow = $pdo->query('SELECT total_slots, opening_time, closing_time FROM parking_settings LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$settings = $settingsRow ?: ['total_slots' => 0, 'opening_time' => null, 'closing_time' => null];
// derive total slots from actual parking_slots rows so Manage Slots updates reflect immediately
$total_slots = (int) $pdo->query('SELECT COUNT(*) FROM parking_slots')->fetchColumn();
// count maintenance slots (if any)
$maintenance = (int) $pdo->query("SELECT COUNT(*) FROM parking_slots WHERE status = 'maintenance'")->fetchColumn();


// Occupied slots = currently active bookings (status 'parked' or 'pending')
// Counts for new widget model
$currently_parked = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'parked'")->fetchColumn();
$active_reservations = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();

// Preserve previous available calculation for other UI parts if needed
$occupied = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status IN ('parked', 'pending')")->fetchColumn();
$available = $total_slots - $maintenance - $occupied;
if ($available < 0) $available = 0;


// Admin: recent bookings; User: my recent bookings
if (isAdmin()) {
    $recent = $pdo->query("
        SELECT b.id, b.status, b.booked_at, u.full_name, v.plate_number, ps.slot_number
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN vehicles v ON b.vehicle_id = v.id
        JOIN parking_slots ps ON b.parking_slot_id = ps.id
        ORDER BY b.created_at DESC LIMIT 10
    ")->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT b.id, b.status, b.booked_at, b.entry_time, b.exit_time, v.plate_number, ps.slot_number,
               GREATEST(TIMESTAMPDIFF(MINUTE, b.entry_time, COALESCE(b.exit_time, NOW())), 0) AS duration_minutes
        FROM bookings b
        JOIN vehicles v ON b.vehicle_id = v.id
        JOIN parking_slots ps ON b.parking_slot_id = ps.id
        WHERE b.user_id = ? ORDER BY b.created_at DESC LIMIT 5
    ");
    $stmt->execute([currentUserId()]);
    $recent = $stmt->fetchAll();
}

// Slots for mini parking map (first 10 by row/col)
$map_slots = [];
if (!isAdmin()) {
    $map_slots = $pdo->query('SELECT id, slot_number, status FROM parking_slots ORDER BY slot_row, slot_column LIMIT 10')->fetchAll();
}

require __DIR__ . '/includes/header.php';
?>

<div class="dashboard-page">
<?php if (!isAdmin()): ?>
<!-- User Dashboard UI -->
<style>
    * {
        font-family: 'Google Sans Flex', sans-serif;
    }
    .dashboard-page {
        max-width: 100%;
    }

    .dashboard-header {
        margin-bottom: 2rem;
        margin-top: 3rem;
        padding: 2rem;
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        border-radius: 16px;
        color: #fff;
        box-shadow: 0 8px 24px rgba(22, 163, 74, 0.15);
    }

    .dashboard-header h3 {
       font-size: 1.75rem;
        font-weight: 700;
        color: #fff;
        margin: 0 0 0.5rem 0;
        letter-spacing: -0.5px;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-family: 'Google Sans Flex', sans-serif;
    }

    .dashboard-header p {
        color: rgba(255, 255, 255, 0.95);
        font-size: 0.95rem;
        margin: 0;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2.5rem;
    }

    .stat-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 1.75rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        position: relative;
        gap: 1.25rem;
        align-items: flex-start;
    }

    .stat-card:hover {
        border-color: #16a34a;
        box-shadow: 0 12px 24px rgba(22, 163, 74, 0.1);
        transform: translateY(-4px);
    }

    .stat-icon {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        flex-shrink: 0;
    }

    .stat-icon.available {
        background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
        color: #15803d;
    }

    .stat-icon.occupied {
        background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%);
        color: #991b1b;
    }

    .stat-icon.total {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        color: #0c4a6e;
    }

    .stat-icon.reservations {
        background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%);
        color: #92400e;
    }

    .stat-label {
        font-size: 0.8rem;
        font-weight: 700;
        color: #9ca3af;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
    }

    .stat-value {
        font-size: 2rem;
        font-weight: 900;
        color: #111;
        letter-spacing: -0.5px;
    }

    .stat-desc {
        font-size: 0.85rem;
        color: #6b7280;
        margin-top: 0.5rem;
    }

    .dashboard-content {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1.75rem;
        margin-bottom: 2.5rem;
    }

    .dashboard-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .dashboard-card:hover {
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        border-color: #16a34a;
    }

    .dashboard-card-header {
        padding: 1.5rem;
        background: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 800;
        font-size: 1.05rem;
        color: #111;
    }

    .dashboard-card-header i {
        color: #16a34a;
        font-size: 1.25rem;
    }

    .dashboard-card-body {
        padding: 1.5rem;
    }

    .slot-map-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
        justify-content: center;
    }

    .slot-mini {
        width: 56px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        font-weight: 800;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        transition: all 0.2s;
        cursor: pointer;
        border: 2px solid transparent;
    }

    .slot-mini:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.12);
    }

    .slot-mini.slot-available {
        background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
        color: #15803d;
        border-color: #86efac;
    }

    .slot-mini.slot-occupied {
        background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%);
        color: #991b1b;
        border-color: #fca5a5;
    }

    .map-legend {
        display: flex;
        gap: 1.5rem;
        padding: 1rem;
        background: #f9fafb;
        border-radius: 10px;
        font-size: 0.9rem;
        font-weight: 600;
        justify-content: center;
        flex-wrap: wrap;
    }

    .map-legend-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #374151;
    }

    .map-legend-box {
        width: 16px;
        height: 16px;
        border-radius: 4px;
    }

    .map-legend-box.available {
        background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
    }

    .map-legend-box.occupied {
        background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%);
    }

    .booking-table {
        width: 100%;
        border-collapse: collapse;
    }

    .booking-table th {
        background: #f9fafb;
        text-align: left;
        padding: 0.85rem 1rem;
        font-weight: 700;
        font-size: 0.8rem;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #e5e7eb;
    }

    .booking-table td {
        padding: 1rem;
        border-bottom: 1px solid #f3f4f6;
        color: #374151;
        font-size: 0.9rem;
    }

    .booking-table tr:hover td {
        background: #f9fafb;
    }

    .status-badge {
        display: inline-block;
        padding: 0.4rem 0.75rem;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .status-pending {
        background: linear-gradient(135deg, #fef3c7 0%, #fcd34d 100%);
        color: #92400e;
    }

    .status-parked {
        background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
        color: #166534;
    }

    .status-completed {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        color: #1e3a8a;
    }

    .status-cancelled {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        color: #991b1b;
    }

    .quick-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .quick-action-btn {
        flex: 1;
        min-width: 140px;
        padding: 1.25rem;
        background: #fff;
        border: 1.5px solid #e5e7eb;
        border-radius: 12px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.75rem;
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
    }

    .quick-action-btn:hover {
        border-color: #16a34a;
        box-shadow: 0 8px 16px rgba(22, 163, 74, 0.12);
        transform: translateY(-2px);
    }

    .quick-action-icon {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
        color: #16a34a;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .quick-action-text {
        font-weight: 700;
        font-size: 0.9rem;
        color: #111;
    }

    @media (max-width: 768px) {
        .dashboard-content {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="dashboard-header">
    <h3><i class="bi bi-speedometer2"></i> Welcome, <?= htmlspecialchars(currentUserName()) ?>!</h3>
    <p>Here's an overview of your parking activity</p>
</div>

<div class="stats-grid">
    <!-- 1. Total Slots (blue) -->
    <div class="stat-card">
        <div class="stat-icon total">
            <i class="bi bi-grid-3x3"></i>
        </div>
        <div>
            <div class="stat-label">Total Slots</div>
            <div class="stat-value"><?= $total_slots ?></div>
            <div class="stat-desc">System capacity</div>
        </div>
    </div>

    <!-- 2. Currently Parked (red) -->
    <div class="stat-card">
        <div class="stat-icon occupied">
            <i class="bi bi-p-square-fill"></i>
        </div>
        <div>
            <div class="stat-label">Currently Parked</div>
            <div class="stat-value"><?= (int) $currently_parked ?></div>
            <div class="stat-desc">Vehicles in lot</div>
        </div>
    </div>

    <!-- 3. Active Reservations (orange) -->
    <div class="stat-card">
        <div class="stat-icon reservations">
            <i class="bi bi-calendar-check-fill"></i>
        </div>
        <div>
            <div class="stat-label">Active Reservations</div>
            <div class="stat-value"><?= (int) $active_reservations ?></div>
            <div class="stat-desc">Upcoming arrivals</div>
        </div>
    </div>
</div>

<div class="dashboard-content">
    <!-- Recent Bookings -->
    <div class="dashboard-card">
        <div class="dashboard-card-header">
            <i class="bi bi-clock-history"></i>
            <span>My Recent Bookings</span>
        </div>
        <div class="dashboard-card-body">
            <?php if (empty($recent)): ?>
                <p class="text-muted mb-0">
  No bookings yet. 
  <a href="<?= BASE_URL ?>/user/book.php" class="text-success fw-bold text-decoration-none">
    Book a slot now!
  </a>
</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="booking-table">
                        <thead>
                            <tr>
                                <th>Slot</th>
                                <th>Plate</th>
                                <th>Status</th>
                                <th>Duration</th>
                                <th>Booked At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent as $r): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($r['slot_number']) ?></strong></td>
                                    <td><?= htmlspecialchars($r['plate_number']) ?></td>
                                    <td><span class="status-badge status-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                                    <td>
                                        <?php 
                                        if (isset($r['duration_minutes'])) {
                                            $mins = (int) $r['duration_minutes'];
                                            $hours = floor($mins / 60);
                                            $remainder = $mins % 60;
                                            echo $hours . 'h ' . $remainder . 'm';
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td><?= date('M j, Y g:i A', strtotime($r['booked_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column -->
    <div>
        <!-- Slot Map Preview -->
        <div class="dashboard-card mb-3">
            <div class="dashboard-card-header">
                <i class="bi bi-grid-3x3-gap"></i>
                <span>Parking Map Preview</span>
            </div>
            <div class="dashboard-card-body">
                <?php if (!empty($map_slots)): ?>
                    <div class="slot-map-grid">
                        <?php foreach ($map_slots as $s): ?>
                            <?php
                                $miniClass = 'available';
                                try {
                                    if ($s['status'] === 'maintenance') {
                                        $miniClass = 'occupied';
                                    } else {
                                        if (isSlotOccupiedNow($pdo, $s['id'])) $miniClass = 'occupied'; else $miniClass = 'available';
                                    }
                                } catch (Exception $e) {
                                    $miniClass = ($s['status'] === 'available') ? 'available' : 'occupied';
                                }
                            ?>
                            <div class="slot-mini slot-<?= $miniClass ?>">
                                <?= htmlspecialchars($s['slot_number']) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="map-legend">
                        <div class="map-legend-item">
                            <div class="map-legend-box available"></div>
                            <span>Available</span>
                        </div>
                        <div class="map-legend-item">
                            <div class="map-legend-box occupied"></div>
                            <span>Occupied</span>
                        </div>
                    </div>
                    <div class="text-center mt-3">
                        <a href="<?= BASE_URL ?>/parking-map.php" class="btn btn-sm" style="background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: #fff; border: none; padding: .5rem .9rem; border-radius: .5rem;">View Full Map</a>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No parking slots configured yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <i class="bi bi-lightning-charge"></i>
                <span>Quick Actions</span>
            </div>
            <div class="dashboard-card-body">
                <div class="quick-actions">
                     <a href="<?= BASE_URL ?>/user/register-car.php" class="quick-action-btn">
                        <div class="quick-action-icon">
                            <i class="bi bi-car-front-fill"></i>
                        </div>
                        <div class="quick-action-text">My Vehicles</div>
                    </a>
                    <a href="<?= BASE_URL ?>/user/book.php" class="quick-action-btn">
                        <div class="quick-action-icon">
                            <i class="bi bi-plus-circle-fill"></i>
                        </div>
                        <div class="quick-action-text">New Booking</div>
                    </a>
                    <a href="<?= BASE_URL ?>/user/booking-history.php" class="quick-action-btn">
                        <div class="quick-action-icon">
                            <i class="bi bi-list-check"></i>
                        </div>
                        <div class="quick-action-text">View All</div>
                    </a>
                   
                    <a href="<?= BASE_URL ?>/user/profile.php" class="quick-action-btn">
                        <div class="quick-action-icon">
                            <i class="bi bi-person-circle"></i>
                        </div>
                        <div class="quick-action-text">Profile</div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Admin Dashboard UI -->
<div class="admin-dashboard-header">
    <h4><i class="bi bi-speedometer2"></i> Admin Dashboard</h4>
    <p>Manage your parking lot efficiently</p>
</div>

<div class="admin-stats-grid mb-4">
    <div class="admin-stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); color: #2563eb;">
            <i class="bi bi-grid-3x3"></i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Total Slots</div>
            <div class="stat-value" style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"><?= $total_slots ?></div>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="stat-icon" style="background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #dc2626;">
            <i class="bi bi-p-square-fill"></i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Currently Parked</div>
            <div class="stat-value" style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"><?= (int) $currently_parked ?></div>
        </div>
    </div>

    <div class="admin-stat-card">    
        <div class="stat-icon" style="background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%); color: #92400e;">
            <i class="bi bi-calendar-check-fill"></i>
        </div>
        <div class="stat-info">
            <div class="stat-label">Active Reservations</div>
            <div class="stat-value" style="background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"><?= (int) $active_reservations ?></div>
        </div>
    </div>
</div>

<!-- Parking Hours Card -->
<div class="admin-info-card mb-3">
    <div class="info-card-header">
        <i class="bi bi-clock-history me-2" style="color: #16a34a;"></i>
        <span>Parking Operating Hours</span>
    </div>
    <div class="info-card-body">
        <div class="hours-display">
            <?php 
            // FIXED: Properly handle time display with null checks
            $opening_display = 'Not Set';
            $closing_display = 'Not Set';
            
            if (!empty($settings['opening_time'])) {
                // Handle both HH:MM:SS and HH:MM formats
                $opening_display = date('g:i A', strtotime($settings['opening_time']));
            }
            
            if (!empty($settings['closing_time'])) {
                // Handle both HH:MM:SS and HH:MM formats
                $closing_display = date('g:i A', strtotime($settings['closing_time']));
            }
            ?>
            <div class="hour-item">
                <span class="hour-label">Opens</span>
                <span class="hour-value"><?= $opening_display ?></span>
            </div>
            <div class="hour-divider">—</div>
            <div class="hour-item">
                <span class="hour-label">Closes</span>
                <span class="hour-value"><?= $closing_display ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Recent Bookings Card -->
<div class="admin-info-card">
    <div class="info-card-header">
        <i class="bi bi-clock-history me-2" style="color: #16a34a;"></i>
        <span>Recent Bookings</span>
    </div>
    <div class="info-card-body">
        <?php if (empty($recent)): ?>
            <p class="text-muted mb-0">No bookings yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Driver</th>
                            <th>Plate</th>
                            <th>Slot</th>
                            <th>Status</th>
                            <th>Booked At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['full_name']) ?></td>
                                <td><strong><?= htmlspecialchars($r['plate_number']) ?></strong></td>
                                <td><?= htmlspecialchars($r['slot_number']) ?></td>
                                <td><span class="status-badge status-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                                <td><?= date('M j, Y g:i A', strtotime($r['booked_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.admin-dashboard-header {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    color: #fff;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
     margin-top: 3rem;
    box-shadow: 0 8px 24px rgba(22, 163, 74, 0.15);
}
.admin-dashboard-header h4 {
    font-size: 1.75rem;
    font-weight: 700;
    color: #fff;
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.admin-dashboard-header p {
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.95rem;
    margin: 0;
}

.admin-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}

.admin-stat-card {
    background: #fff;
    border: 1px solid #e5f2e8;
    border-radius: 14px;
    padding: 1.5rem;
    display: grid;
    grid-template-columns: 70px 1fr;
    gap: 1.5rem;
    align-items: center;
    transition: all cubic-bezier(0.4, 0, 0.2, 1) 0.3s;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}
.admin-stat-card:hover {
    border-color: #16a34a;
    box-shadow: 0 8px 24px rgba(22, 163, 74, 0.12);
    transform: translateY(-2px);
}

.stat-icon {
    width: 70px;
    height: 70px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.stat-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.stat-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-value {
    font-size: 1.8rem;
    font-weight: 700;
}

.admin-info-card {
    background: #fff;
    border: 1px solid #e5f2e8;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 4px 16px rgba(22, 163, 74, 0.08);
}

.info-card-header {
    background: linear-gradient(135deg, #f0fdf4 0%, #fafcfb 100%);
    border-bottom: 1px solid #e5f2e8;
    padding: 1.25rem 1.5rem;
    font-weight: 700;
    font-size: 1rem;
    color: #374151;
    display: flex;
    align-items: center;
}

.info-card-body {
    padding: 1.5rem;
}

.hours-display {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    justify-content: center;
}

.hour-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: center;
}

.hour-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.hour-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #16a34a;
}

.hour-divider {
    color: #d1d5db;
    font-size: 1.5rem;
    font-weight: 300;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.admin-table thead {
    background: linear-gradient(135deg, #f0fdf4 0%, #fafcfb 100%);
}

.admin-table th {
    padding: 1rem 1.25rem;
    text-align: left;
    font-weight: 700;
    font-size: 0.8rem;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e5f2e8;
}

.admin-table td {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #f0fdf4;
    color: #374151;
    font-size: 0.9rem;
}

.admin-table tbody tr {
    transition: all cubic-bezier(0.4, 0, 0.2, 1) 0.3s;
}

.admin-table tbody tr:hover {
    background: #f9fdfb;
    border-color: #e5f2e8;
}

.admin-table tbody tr:last-child td {
    border-bottom: none;
}

.status-badge {
    display: inline-block;
    padding: 0.5rem 0.85rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
}

.status-badge.status-pending {
    background: linear-gradient(135deg, #fef3c7 0%, #fcd34d 100%);
    color: #92400e;
}

.status-badge.status-parked {
    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
    color: #166534;
}

.status-badge.status-completed {
    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
    color: #166534;
}

.status-badge.status-cancelled {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #991b1b;
}
</style>

<?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>