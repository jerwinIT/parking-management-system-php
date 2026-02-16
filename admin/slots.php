<?php
/**
 * Admin - Manage total number of parking slots (add/remove slots)
 */
define('PARKING_ACCESS', true);
require_once dirname(__DIR__) . '/config/init.php';
requireAdmin();

$page_title = 'Manage Parking Slots';
$current_page = 'admin-slots';
$message = '';
$pdo = getDB();

// Handle slot maintenance update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_slot_maintenance'])) {
    $slot_id = (int) ($_POST['slot_id'] ?? 0);
    $new_status = trim($_POST['slot_status'] ?? '');
    
    // Log for debugging
    error_log("MAINTENANCE UPDATE - Slot ID: $slot_id, New Status: '$new_status'");
    
    if ($slot_id && in_array($new_status, ['available', 'maintenance'])) {
        error_log("UPDATE ALLOWED - Executing database update");
        $pdo->prepare('UPDATE parking_slots SET status = ? WHERE id = ?')->execute([$new_status, $slot_id]);
        error_log("UPDATE COMPLETE - Slot $slot_id set to status '$new_status'");
        setAlert('Slot maintenance status updated successfully.', 'success');
        header('Location: ' . BASE_URL . '/admin/slots.php');
        exit;
    } else {
        error_log("UPDATE BLOCKED - Validation failed. Slot ID valid: " . ($slot_id ? 'yes' : 'no') . ", Status valid: " . (in_array($new_status, ['available', 'maintenance']) ? 'yes' : 'no'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_hours') {
        $opening_time = trim($_POST['opening_time'] ?? '');
        $closing_time = trim($_POST['closing_time'] ?? '');
        
        // Debug logging
        error_log("HOURS UPDATE - Opening: '$opening_time', Closing: '$closing_time'");
        
        // Validate that opening and closing times are not equal
        if ($opening_time === $closing_time) {
            setAlert('Opening and closing times cannot be the same.', 'danger');
            header('Location: ' . BASE_URL . '/admin/slots.php');
            exit;
        }
        
        // Validate that the time span does not exceed 16 hours
        // Use today's date as a reference for proper time calculation
        $today = date('Y-m-d');
        $opening_timestamp = strtotime($today . ' ' . $opening_time);
        $closing_timestamp = strtotime($today . ' ' . $closing_time);
        
        // Debug: Log the timestamps
        error_log("HOURS UPDATE - Opening timestamp: $opening_timestamp (" . date('Y-m-d H:i:s', $opening_timestamp) . ")");
        error_log("HOURS UPDATE - Closing timestamp: $closing_timestamp (" . date('Y-m-d H:i:s', $closing_timestamp) . ")");
        
        // Calculate the difference in hours
        $diff_seconds = $closing_timestamp - $opening_timestamp;
        
        // If closing time is earlier than opening time, it means it's next day
        if ($diff_seconds < 0) {
            $diff_seconds += 24 * 3600; // Add 24 hours
        }
        
        $diff_hours = $diff_seconds / 3600;
        
        // Debug: Log the calculated hours
        error_log("HOURS UPDATE - Calculated hours: $diff_hours");
        
        // Validate minimum of 1 hour (to catch any edge cases where times might be equal)
        if ($diff_hours < 1) {
            setAlert('Operating hours must be at least 1 hour.', 'danger');
            header('Location: ' . BASE_URL . '/admin/slots.php');
            exit;
        }
        
        if ($diff_hours > 16) {
            setAlert('Operating hours cannot exceed 16 hours. Please adjust the closing time.', 'danger');
            header('Location: ' . BASE_URL . '/admin/slots.php');
            exit;
        }
        
        // FIXED: Check if row exists, if not insert it
        $checkRow = $pdo->query('SELECT COUNT(*) FROM parking_settings')->fetchColumn();
        error_log("HOURS UPDATE - Rows in parking_settings: $checkRow");
        
        if ($checkRow == 0) {
            // Insert a new row if none exists
            error_log("HOURS UPDATE - Inserting new row");
            $stmt = $pdo->prepare('INSERT INTO parking_settings (total_slots, opening_time, closing_time) VALUES (0, :opening, :closing)');
            $stmt->execute([':opening' => $opening_time, ':closing' => $closing_time]);
        } else {
            // Update existing row
            error_log("HOURS UPDATE - Updating existing row");
            $stmt = $pdo->prepare('UPDATE parking_settings SET opening_time = :opening, closing_time = :closing');
            $stmt->execute([':opening' => $opening_time, ':closing' => $closing_time]);
        }
        
        // Verify the save
        $verify = $pdo->query('SELECT opening_time, closing_time FROM parking_settings LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        error_log("HOURS UPDATE - Saved values: Opening=" . ($verify['opening_time'] ?? 'NULL') . ", Closing=" . ($verify['closing_time'] ?? 'NULL'));
        
        setAlert('Parking hours updated successfully.', 'success');
        header('Location: ' . BASE_URL . '/admin/slots.php');
        exit;
    } elseif ($action === 'update_slots') {
        $total = (int) ($_POST['total_slots'] ?? 0);
        if ($total < 1 || $total > 200) {
            setAlert('Total slots must be between 1 and 200.', 'danger');
        } else {
            $current = $pdo->query('SELECT COUNT(*) FROM parking_slots')->fetchColumn();
            if ($total > $current) {
                $cols_per_row = 5;
                for ($i = $current + 1; $i <= $total; $i++) {
                    $row = (int) ceil($i / $cols_per_row);
                    $col = (($i - 1) % $cols_per_row) + 1;
                    $letter = chr(64 + $row);
                    $slot_num = $letter . $col;
                    $pdo->prepare('INSERT INTO parking_slots (slot_number, slot_row, slot_column, status) VALUES (?, ?, ?, ?)')->execute([$slot_num, $row, $col, 'available']);
                }
            } elseif ($total < $current) {
                $to_remove = $current - $total;
                $slots = $pdo->query('SELECT id FROM parking_slots WHERE status = "available" ORDER BY id DESC LIMIT ' . (int) $to_remove)->fetchAll(PDO::FETCH_COLUMN);
                foreach ($slots as $id) {
                    $pdo->prepare('DELETE FROM parking_slots WHERE id = ?')->execute([$id]);
                }
            }
            // optionally update slot prefix if provided
            $slot_prefix = trim($_POST['slot_prefix'] ?? '');
            $needPrefixCol = !$pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parking_settings' AND COLUMN_NAME = 'slot_prefix'")->fetchColumn();
            if ($needPrefixCol) {
                $pdo->exec("ALTER TABLE parking_settings ADD COLUMN slot_prefix VARCHAR(10) DEFAULT 'A'");
            }
            if ($slot_prefix !== '') {
                $stmt = $pdo->prepare('UPDATE parking_settings SET total_slots = :total, slot_prefix = :prefix');
                $stmt->execute([':total' => $total, ':prefix' => $slot_prefix]);
            } else {
                $stmt = $pdo->prepare('UPDATE parking_settings SET total_slots = :total');
                $stmt->execute([':total' => $total]);
            }
            setAlert('Parking slots saved to the database.', 'success');
            header('Location: ' . BASE_URL . '/admin/slots.php');
            exit;
        }
    } elseif ($action === 'update_pricing') {
        // ensure columns exist, then update
        $needPriceCol = !$pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parking_settings' AND COLUMN_NAME = 'price_per_hour'")->fetchColumn();
        $needMaxCol = !$pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parking_settings' AND COLUMN_NAME = 'max_booking_hours'")->fetchColumn();
        if ($needPriceCol) {
            $pdo->exec("ALTER TABLE parking_settings ADD COLUMN price_per_hour DECIMAL(10,2) DEFAULT 0");
        }
        if ($needMaxCol) {
            $pdo->exec("ALTER TABLE parking_settings ADD COLUMN max_booking_hours INT DEFAULT NULL");
        }
        $price = (float) str_replace(',', '.', trim($_POST['price_per_hour'] ?? ''));
        $maxh = (int) ($_POST['max_booking_hours'] ?? 0);
        
        // Validate price: must be greater than 0
        if ($price <= 0) {
            setAlert('Price per hour must be greater than zero.', 'danger');
            header('Location: ' . BASE_URL . '/admin/slots.php');
            exit;
        }
        
        if ($maxh < 0) $maxh = 0;
        $stmt = $pdo->prepare('UPDATE parking_settings SET price_per_hour = :price, max_booking_hours = :maxh');
        $stmt->execute([':price' => $price, ':maxh' => $maxh]);
        setAlert('Pricing settings updated successfully.', 'success');
        header('Location: ' . BASE_URL . '/admin/slots.php');
        exit;
    } else {
        // legacy/combined post: handle both if provided
        $total = (int) ($_POST['total_slots'] ?? 0);
        $opening_time = trim($_POST['opening_time'] ?? '');
        $closing_time = trim($_POST['closing_time'] ?? '');
        if ($total >= 1 && $total <= 200) {
            $current = $pdo->query('SELECT COUNT(*) FROM parking_slots')->fetchColumn();
            if ($total > $current) {
                $cols_per_row = 5;
                for ($i = $current + 1; $i <= $total; $i++) {
                    $row = (int) ceil($i / $cols_per_row);
                    $col = (($i - 1) % $cols_per_row) + 1;
                    $letter = chr(64 + $row);
                    $slot_num = $letter . $col;
                    $pdo->prepare('INSERT INTO parking_slots (slot_number, slot_row, slot_column, status) VALUES (?, ?, ?, ?)')->execute([$slot_num, $row, $col, 'available']);
                }
            } elseif ($total < $current) {
                $to_remove = $current - $total;
                $slots = $pdo->query('SELECT id FROM parking_slots WHERE status = "available" ORDER BY id DESC LIMIT ' . (int) $to_remove)->fetchAll(PDO::FETCH_COLUMN);
                foreach ($slots as $id) {
                    $pdo->prepare('DELETE FROM parking_slots WHERE id = ?')->execute([$id]);
                }
            }
            $stmt = $pdo->prepare('UPDATE parking_settings SET total_slots = :total, opening_time = :opening, closing_time = :closing');
            $stmt->execute([':total' => $total, ':opening' => $opening_time, ':closing' => $closing_time]);
            setAlert('Parking slots saved to the database.', 'success');
            header('Location: ' . BASE_URL . '/admin/slots.php');
            exit;
        } else {
            setAlert('Total slots must be between 1 and 200.', 'danger');
        }
    }
}

// handle success flag
if (isset($_GET['updated'])) {
    setAlert('Parking slots saved to the database.', 'success');
}
if (isset($_GET['maintenance_updated'])) {
    setAlert('Slot maintenance status updated successfully.', 'success');
}
$settingsRow = $pdo->query('SELECT * FROM parking_settings LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$total_slots = $settingsRow['total_slots'] ?? 0;
$opening_time = $settingsRow['opening_time'] ?? '';
$closing_time = $settingsRow['closing_time'] ?? '';
$price_per_hour = isset($settingsRow['price_per_hour']) ? $settingsRow['price_per_hour'] : '';
$max_booking_hours = isset($settingsRow['max_booking_hours']) ? $settingsRow['max_booking_hours'] : '';
$slot_prefix = isset($settingsRow['slot_prefix']) ? $settingsRow['slot_prefix'] : 'A';
$slot_count = $pdo->query('SELECT COUNT(*) FROM parking_slots')->fetchColumn();
$slots = $pdo->query('SELECT id, slot_number, status FROM parking_slots ORDER BY slot_row, slot_column')->fetchAll();

require dirname(__DIR__) . '/includes/header.php';
?>

<?php
// compute counts
$total_slots_db = (int) $slot_count;
$total_slots_setting = (int) $total_slots;
$total = $total_slots_db > 0 ? $total_slots_db : $total_slots_setting;
$available = (int) $pdo->query("SELECT COUNT(*) FROM parking_slots WHERE status = 'available'")->fetchColumn();
// Occupied slots = currently active bookings (status 'parked' or 'pending')
$occupied = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status IN ('parked', 'pending')")->fetchColumn();

// Debug logging
error_log("SLOTS COUNT - Total: $total, Available: $available, Occupied: $occupied");

// group slots by floor/row
$all_slots = $pdo->query('SELECT id, slot_number, slot_row, slot_column, status FROM parking_slots ORDER BY slot_row, slot_column')->fetchAll(PDO::FETCH_ASSOC);
$floors = [];
foreach ($all_slots as $s) {
    $row = $s['slot_row'] ?? 1;
    $floors[$row][] = $s;
}
ksort($floors);
?>

<style>
    .slots-header {
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

    .slots-header h3 {
        font-weight: 800;
        font-size: 1.75rem;
        margin-bottom: 0.5rem;
        color: #fff;
        letter-spacing: -0.5px;
    }
    .slots-header-pp {
        color: rgba(255, 255, 255, 0.9);
        font-size: 0.95rem;
        margin: 0;
    }

    .slots-header .text-muted {
        color: rgba(255, 255, 255, 0.95);
    }

    .slots-header .btn {
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: #fff;
        font-weight: 700;
        transition: all 0.2s ease;
    }

    .slots-header .btn:hover {
        background: rgba(255, 255, 255, 0.3);
        border-color: rgba(255, 255, 255, 0.5);
        transform: translateY(-2px);
    }

    .slots-stats {
        display: flex;
        gap: 1.5rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }

    .stat-card {
        flex: 1;
        min-width: 200px;
        background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
        border-radius: 14px;
        padding: 1.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        border: 1px solid #e5e7eb;
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    }

    .stat-card .label {
        font-size: 0.75rem;
        color: #9ca3af;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
    }

    .stat-card .num {
        font-size: 2rem;
        font-weight: 800;
        color: #16a34a;
        letter-spacing: -1px;
    }

    .stat-card.pink {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        color: #b91c1c;
    }

    .stat-card.pink .num {
        color: #dc2626;
    }

    .stat-card.orange {
        background: linear-gradient(135deg, #fef08a 0%, #fde047 100%);
        color: #854d0e;
    }

    .stat-card.orange .num {
        color: #ca8a04;
    }

    .stat-meta {
        font-size: 0.85rem;
        color: #6b7280;
        margin-top: 0.5rem;
    }

    .occupancy-banner {
        background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        border-radius: 14px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        border: 2px solid #86efac;
        box-shadow: 0 4px 12px rgba(22, 163, 74, 0.1);
    }

    .occupancy-banner strong {
        color: #15803d;
        font-weight: 800;
        font-size: 1.1rem;
    }

    .occupancy-banner .text-muted {
        color: #16a34a;
        margin-top: 0.5rem;
    }

    .floor-card {
        border-radius: 14px;
        padding: 1.75rem;
        background: #fff;
        margin-bottom: 2rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        border: 1px solid #e5e7eb;
        transition: all 0.3s ease;
    }

    .floor-card:hover {
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        border-color: #16a34a;
    }

    .floor-card h5 {
        font-weight: 800;
        color: #111;
        font-size: 1.1rem;
        letter-spacing: -0.5px;
    }

    .floor-card .text-muted {
        color: #9ca3af;
        font-size: 0.85rem;
    }

    .slot-box {
        width: 140px;
        height: 120px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        color: #fff;
        margin: 0.5rem;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        border: 2px solid;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .slot-box::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at top left, rgba(255, 255, 255, 0.2), transparent);
        pointer-events: none;
    }

    .slot-box.available {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        border-color: #16a34a;
    }

    .slot-box.available:hover {
        transform: translateY(-6px) scale(1.05);
        box-shadow: 0 8px 20px rgba(22, 163, 74, 0.3);
    }

    .slot-box.occupied {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        border-color: #ef4444;
    }

    .slot-box.occupied:hover {
        transform: translateY(-6px);
        box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
    }

    .slot-box.maintenance {
        background: linear-gradient(135deg, #fef08a 0%, #fde047 100%);
        color: #854d0e;
        border-color: #facc15;
    }

    .slot-box.maintenance:hover {
        transform: translateY(-6px);
        box-shadow: 0 8px 20px rgba(202, 138, 4, 0.3);
    }

    .slot-box.pending {
        background: linear-gradient(135deg, #eab308 0%, #ca8a04 100%);
        border-color: #eab308;
    }

    .slot-box.pending:hover {
        transform: translateY(-6px);
        box-shadow: 0 8px 20px rgba(234, 179, 8, 0.3);
    }

    .slot-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        padding: 0.5rem;
    }

    /* Settings modal styling */
    .settings-modal .modal-content {
        border-radius: 16px;
        border: 1px solid #e5e7eb;
    }

    .settings-modal .modal-header {
        border-bottom: 2px solid #f3f4f6;
        padding: 1.5rem;
        background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
    }

    .settings-modal .nav-tabs {
        border-bottom: 2px solid #e5e7eb;
        gap: 0.5rem;
    }

    .settings-modal .nav-tabs .nav-link {
        padding: 0.9rem 1.4rem;
        color: #6b7280;
        border: none;
        border-bottom: 3px solid transparent;
        font-weight: 700;
        transition: all 0.2s ease;
        text-transform: capitalize;
    }

    .settings-modal .nav-tabs .nav-link:hover {
        color: #16a34a;
    }

    .settings-modal .nav-tabs .nav-link.active {
        color: #15803d;
        border-bottom-color: #16a34a;
        background: linear-gradient(to bottom, rgba(22, 163, 74, 0.05), transparent);
    }

    .settings-modal .info-box {
        background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        border: 2px solid #86efac;
        padding: 1rem;
        border-radius: 12px;
        color: #15803d;
        font-weight: 500;
    }

    .settings-modal .operating-box {
        background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        border: 2px solid #86efac;
        padding: 1rem;
        border-radius: 12px;
        margin-top: 1rem;
        color: #15803d;
        font-weight: 700;
    }

    .settings-modal .input-with-icon .input-group-text {
        background: transparent;
        border: none;
        color: #16a34a;
        font-weight: 700;
    }

    .settings-modal .modal-footer-custom {
        display: flex;
        gap: 1rem;
        align-items: center;
        justify-content: flex-end;
        margin-top: 1.5rem;
    }

    .settings-modal .modal-footer-custom .btn {
        min-width: 120px;
        padding: 0.75rem 1.5rem;
        border-radius: 10px;
        font-weight: 700;
        transition: all 0.2s ease;
    }

    .settings-modal .modal-footer-custom .btn-light {
        background: #fff;
        border: 1px solid #d1d5db;
        color: #374151;
    }

    .settings-modal .modal-footer-custom .btn-light:hover {
        background: #f3f4f6;
        border-color: #9ca3af;
        color: #111;
    }

    .settings-modal .modal-footer-custom .btn-success {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        border: none;
        color: #fff;
        box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
    }

    .settings-modal .modal-footer-custom .btn-success:hover {
        box-shadow: 0 6px 16px rgba(22, 163, 74, 0.4);
        transform: translateY(-2px);
    }

    .settings-modal .modal-settings-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.25rem;
        border-radius: 12px;
        background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
        border: 1px solid #e5e7eb;
        margin-bottom: 1.5rem;
    }

    .settings-modal .modal-settings-header h5 {
        margin: 0;
        font-weight: 800;
        color: #111;
        font-size: 1.05rem;
    }

    @media (max-width: 768px) {
        .slots-header {
            flex-direction: column;
            align-items: flex-start;
            padding: 1.5rem;
        }

        .slots-stats {
            flex-direction: column;
            gap: 1rem;
        }

        .stat-card {
            width: 100%;
        }

        .slot-box {
            width: 130px;
            height: 110px;
            font-size: 0.9rem;
        }
    }
</style>

<div class="slots-header">
    <div class="slots-header-p">
        <h3>Manage Parking Slots</h3>
        <p class="mb-0" style="color: rgba(255, 255, 255, 0.9); font-size: 0.95rem;">View and manage all parking spaces in real-time</p>
    </div>
    <div>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#settingsModal"><i class="bi bi-gear me-2"></i> Settings</button>
    </div>
</div>

<div class="slots-stats">
    <div class="stat-card">
        <div class="label">Total Slots</div>
        <div class="num"><?= $total ?></div>
        <div class="stat-meta">All spaces</div>
    </div>
    <div class="stat-card">
        <div class="label">Available</div>
        <div class="num"><?= $available ?></div>
        <div class="stat-meta"><?= ($total ? round(($available / $total) * 100) : 0) ?>% free</div>
    </div>
    <div class="stat-card pink">
        <div class="label">Occupied</div>
        <div class="num"><?= $occupied ?></div>
        <div class="stat-meta">Currently parked</div>
    </div>
</div>


<?php foreach ($floors as $floorNum => $items): ?>
    <div class="floor-card">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h5 class="mb-1">Row <?= htmlspecialchars($floorNum) ?></h5>
                <div class="text-muted small"><?= count(array_filter($items, fn($s) => $s['status'] === 'available')) ?> available of <?= count($items) ?> spaces</div>
            </div>
        </div>
        <div class="slot-grid">
            <?php foreach ($items as $s): ?>
                <?php 
                    $cls = $s['status'] === 'available' ? 'available' : ($s['status'] === 'maintenance' ? 'maintenance' : ($s['status'] === 'pending' ? 'pending' : 'occupied'));
                    $slot_display = htmlspecialchars($s['slot_number']);
                    $slot_status = htmlspecialchars($s['status']);
                    $slot_id = (int)$s['id'];
                    $floor_num = $s['slot_row'] ?? 1;
                    $onclick_call = "openSlotModal({$slot_id}, '{$slot_display}', '{$slot_status}', {$floor_num})";
                ?>
                <div class="slot-box <?= $cls ?>" onclick="<?= $onclick_call ?>" title="<?= $slot_display ?>">
                    <div style="text-align:center; position:relative; z-index:1;">
                        <div style="font-weight:800; font-size:1rem;"><?= $slot_display ?></div>
                        <?php if ($s['status'] !== 'available'): ?>
                            <div style="font-size:0.75rem; margin-top:6px; opacity:0.9;">
                            <?php
                                $b = $pdo->prepare('SELECT entry_time, exit_time FROM bookings WHERE parking_slot_id = ? AND status = ? ORDER BY entry_time DESC LIMIT 1');
                                $b->execute([$s['id'], 'parked']);
                                $br = $b->fetch(PDO::FETCH_ASSOC);
                                if ($br && !empty($br['entry_time'])) echo date('g:i A', strtotime($br['entry_time']));
                            ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

<!-- Settings Modal -->
<div class="modal fade settings-modal" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-none" id="settingsModalLabel">Manage Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="modal-settings-header mb-3">
                    <div>
                        <h5 style="margin:0">Parking Lot Settings</h5>
                        <div class="text-muted small">Configure availability, naming and pricing</div>
                    </div>
                </div>
                <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="manage-hours-tab" data-bs-toggle="tab" data-bs-target="#manage-hours" type="button" role="tab" aria-controls="manage-hours" aria-selected="true"><i class="bi bi-clock me-1"></i> Update Hours</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="update-slots-tab" data-bs-toggle="tab" data-bs-target="#update-slots" type="button" role="tab" aria-controls="update-slots" aria-selected="false"><i class="bi bi-grid-3x3 me-1"></i> Update Slots</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pricing-tab" data-bs-toggle="tab" data-bs-target="#pricing" type="button" role="tab" aria-controls="pricing" aria-selected="false"><i class="bi bi-currency-dollar me-1"></i> Pricing</button>
                    </li>
                </ul>
                <div class="tab-content mt-3">
                    <div class="tab-pane fade show active" id="manage-hours" role="tabpanel" aria-labelledby="manage-hours-tab">
                        <div class="info-box mb-3">Set the operating hours for your parking lot. Bookings will only be allowed during these hours.</div>
                        <form method="post" action="<?= BASE_URL ?>/admin/slots.php">
                            <input type="hidden" name="action" value="update_hours">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Opening Time</label>
                                    <div class="input-group input-with-icon">
                                        <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                        <input type="time" name="opening_time" class="form-control" value="<?= htmlspecialchars($opening_time) ?>">
                                    </div>
                                    <div class="form-text">When customers can start booking</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Closing Time</label>
                                    <div class="input-group input-with-icon">
                                        <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                        <input type="time" name="closing_time" class="form-control" value="<?= htmlspecialchars($closing_time) ?>">
                                    </div>
                                    <div class="form-text">When customers can no longer book</div>
                                </div>
                            </div>
                            <div class="operating-box">
                                <strong>Operating Hours</strong>
                                <div class="mt-2"><?php
                                    // FIXED: Properly format time values
                                    $fmtOpen = '--';
                                    $fmtClose = '--';
                                    
                                    if (!empty($opening_time)) {
                                        list($h, $m) = array_pad(explode(':', $opening_time), 2, '0');
                                        $fmtOpen = date('g:i A', mktime($h, $m));
                                    }
                                    
                                    if (!empty($closing_time)) {
                                        list($h, $m) = array_pad(explode(':', $closing_time), 2, '0');
                                        $fmtClose = date('g:i A', mktime($h, $m));
                                    }
                                    
                                    echo htmlspecialchars($fmtOpen . ' - ' . $fmtClose);
                                ?></div>
                            </div>
                            <div class="modal-footer-custom">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-success">Save Changes</button>
                            </div>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="update-slots" role="tabpanel" aria-labelledby="update-slots-tab">
                        <div class="info-box mb-3">Manage the total number of parking slots in your lot. Current total: <?= (int) $slot_count ?></div>
                        <form method="post" action="<?= BASE_URL ?>/admin/slots.php">
                            <input type="hidden" name="action" value="update_slots">
                      
                                <div class="col-md-6">
                                    <label class="form-label">Total Parking Slots</label>
                                    <input type="number" name="total_slots" class="form-control" min="1" max="200" value="<?= (int) $total_slots ?>" required>
                                    <div class="form-text">Number of available parking spaces</div>
                                </div>
                               
                    
                            <div class="modal-footer-custom">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Update Slots</button>
                            </div>
                        </form>
                    </div>
                        <div class="tab-pane fade" id="pricing" role="tabpanel" aria-labelledby="pricing-tab">
                            <form method="post" action="<?= BASE_URL ?>/admin/slots.php">
                                <input type="hidden" name="action" value="update_pricing">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-6">
                                        <label class="form-label">Price Per Hour (₱)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">₱</span>
                                            <input type="number" step="0.01" min="0.01" name="price_per_hour" class="form-control" value="<?= htmlspecialchars($price_per_hour) ?>" required>
                                        </div>
                                        <div class="form-text">Hourly parking rate</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Max Booking Duration (hours)</label>
                                        <input type="number" name="max_booking_hours" class="form-control" min="1" value="<?= htmlspecialchars($max_booking_hours) ?>">
                                        <div class="form-text">Maximum consecutive booking hours</div>
                                    </div>
                                    <div class="col-12 text-end mt-2">
                                        <button type="submit" class="btn btn-success">Save Changes</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

<script>
function openSlotModal(slotId, slotNumber, currentStatus, floor) {
    console.log('=== OPEN SLOT MODAL ===');
    console.log('Slot ID:', slotId);
    console.log('Slot Number:', slotNumber);
    console.log('Current Status from DB:', currentStatus);
    console.log('Floor:', floor);
    
    // Set display values
    document.getElementById('slotModalNumber').textContent = slotNumber;
    document.getElementById('slotFloorNumber').textContent = floor;
    document.getElementById('slotModalId').value = slotId;
    document.getElementById('slotMaintenanceStatus').value = currentStatus;
    
    console.log('Hidden slotModalId set to:', document.getElementById('slotModalId').value);
    console.log('Hidden slotMaintenanceStatus set to:', document.getElementById('slotMaintenanceStatus').value);
    
    // Determine which option should be selected based on current status
    const maintenanceRadio = document.querySelector('input[value="maintenance"][name="status_choice"]');
    const availableRadio = document.querySelector('input[value="available"][name="status_choice"]');
    const maintenanceOption = document.getElementById('maintenanceOption');
    const doneOption = document.getElementById('doneOption');
    
    if (currentStatus === 'maintenance') {
        maintenanceRadio.checked = true;
        availableRadio.checked = false;
        maintenanceOption.style.borderColor = '#10b981';
        maintenanceOption.style.backgroundColor = '#f0fdf4';
        doneOption.style.borderColor = '#d1d5db';
        doneOption.style.backgroundColor = '#fff';
        console.log('Modal initialized for MAINTENANCE status');
    } else {
        availableRadio.checked = true;
        maintenanceRadio.checked = false;
        doneOption.style.borderColor = '#10b981';
        doneOption.style.backgroundColor = '#f0fdf4';
        maintenanceOption.style.borderColor = '#d1d5db';
        maintenanceOption.style.backgroundColor = '#fff';
        console.log('Modal initialized for AVAILABLE status');
    }
    
    console.log('=====================');
    const modal = new bootstrap.Modal(document.getElementById('slotMaintenanceModal'));
    modal.show();
}

function selectMaintenanceStatus(status) {
    console.log('selectMaintenanceStatus called with:', status);
    
    // Get the hidden field that will be submitted
    const statusField = document.getElementById('slotMaintenanceStatus');
    const maintenanceOption = document.getElementById('maintenanceOption');
    const doneOption = document.getElementById('doneOption');
    const maintenanceRadio = document.querySelector('input[value="maintenance"][name="status_choice"]');
    const availableRadio = document.querySelector('input[value="available"][name="status_choice"]');
    
    // Set the hidden field value
    statusField.value = status;
    console.log('Hidden field value set to:', statusField.value);
    
    // Update radio button states
    if (status === 'maintenance') {
        maintenanceRadio.checked = true;
        availableRadio.checked = false;
        maintenanceOption.style.borderColor = '#10b981';
        maintenanceOption.style.backgroundColor = '#f0fdf4';
        doneOption.style.borderColor = '#d1d5db';
        doneOption.style.backgroundColor = '#fff';
        console.log('Selected: Maintenance');
    } else if (status === 'available') {
        availableRadio.checked = true;
        maintenanceRadio.checked = false;
        doneOption.style.borderColor = '#10b981';
        doneOption.style.backgroundColor = '#f0fdf4';
        maintenanceOption.style.borderColor = '#d1d5db';
        maintenanceOption.style.backgroundColor = '#fff';
        console.log('Selected: Available');
    }
    
    console.log('Final hidden field value:', document.getElementById('slotMaintenanceStatus').value);
}

// Validate form submission and ensure status is set
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('slotMaintenanceForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const slotId = document.getElementById('slotModalId').value;
            const statusField = document.getElementById('slotMaintenanceStatus');
            const status = statusField.value;
            
            console.log('=== FORM SUBMIT DEBUG ===');
            console.log('Slot ID:', slotId);
            console.log('Status Field Value:', status);
            console.log('Status Field Element:', statusField);
            
            // CRITICAL: If status is empty, take it from the radio button
            if (!status || status === '') {
                const selectedRadio = document.querySelector('input[name="status_choice"]:checked');
                if (selectedRadio) {
                    statusField.value = selectedRadio.value;
                    console.log('Set status from radio:', selectedRadio.value);
                } else {
                    e.preventDefault();
                    alert('Please select a maintenance status by clicking "Under Maintenance" or "Maintenance Done".');
                    return false;
                }
            }
            
            if (!slotId) {
                e.preventDefault();
                alert('Slot ID not found. Please try again.');
                return false;
            }
            
            if (statusField.value !== 'maintenance' && statusField.value !== 'available') {
                e.preventDefault();
                alert('Invalid maintenance status: ' + statusField.value);
                return false;
            }
            
            console.log('Form validation passed. Submitting with status:', statusField.value);
            console.log('=========================');
        });
    }
});
</script>