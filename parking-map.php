<?php
/**
 * Parking Map - Visual representation of slots (available/occupied, cars inside)
 * Updates dynamically when car enters/exits (refresh or AJAX)
 */
define('PARKING_ACCESS', true);
require_once __DIR__ . '/config/init.php';
requireLogin();

$page_title = 'Parking Map';
$current_page = 'map';

$pdo = getDB();
// Fetch basic slot info; occupancy and parked vehicle info will be determined via helpers
$slots = $pdo->query('SELECT ps.id, ps.slot_number, ps.slot_row, ps.slot_column, ps.status FROM parking_slots ps ORDER BY ps.slot_row, ps.slot_column')->fetchAll();

// Group by row for grid display
$by_row = [];
foreach ($slots as $s) {
    $by_row[$s['slot_row']][] = $s;
}

require __DIR__ . '/includes/header.php';
?>

<div class="parking-map-page">
    <div class="parking-map-header">
        <h3><i class="bi bi-map"></i>Parking Lot Layout</h3>
        <p>Click on a slot to view details</p>
    </div>

    <div class="parking-controls">
        <div class="parking-legend">
            <div class="legend-item">
                <div class="legend-box legend-available"></div>
                <span>Available</span>
            </div>
            <div class="legend-item">
                <div class="legend-box legend-occupied"></div>
                <span>Occupied</span>
            </div>
        </div>
        <div class="parking-controls-right">
            <label>
                <input type="checkbox" id="autoRefresh" class="me-1"> Auto-refresh (30s)
            </label>
            <a href="<?= BASE_URL ?>/parking-map.php" class="btn btn-sm btn-outline-primary" style="background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: #fff; border: none;">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </a>
        </div>
    </div>

    <div class="parking-grid">
        <?php foreach ($by_row as $row_num => $row_slots): ?>
            <div>
                <div class="parking-grid-row-label">Row <?= htmlspecialchars($row_num) ?></div>
                <div class="parking-grid-row" data-row="<?= $row_num ?>">
                    <?php foreach ($row_slots as $slot): ?>
                        <?php
                            // Determine dynamic state
                            $stateClass = 'slot-available';
                            if ($slot['status'] === 'maintenance') {
                                $stateClass = 'slot-maintenance';
                            } else {
                                try {
                                    if (isSlotOccupiedNow($pdo, $slot['id'])) {
                                        $stateClass = 'slot-occupied';
                                    } else {
                                        $stateClass = 'slot-available';
                                    }
                                } catch (Exception $e) {
                                    $stateClass = ($slot['status'] === 'available') ? 'slot-available' : 'slot-occupied';
                                }
                            }
                            $parkedInfo = getParkedBookingInfo($pdo, $slot['id']);
                        ?>
                        <div class="slot-box <?= $stateClass ?>" data-slot="<?= htmlspecialchars($slot['slot_number']) ?>">
                            <div class="slot-content">
                                <div class="slot-number"><?= htmlspecialchars($slot['slot_number']) ?></div>
                                <?php if (!empty($parkedInfo['plate_number'])): ?>
                                    <div class="slot-vehicle-info"><?= htmlspecialchars($parkedInfo['plate_number'] . ' - ' . ($parkedInfo['full_name'] ?? '')) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($parkedInfo['entry_time'])): ?>
                                    <div class="slot-entry-time"><?= date('g:i A', strtotime($parkedInfo['entry_time'])) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="slot-status"><?= htmlspecialchars(ucfirst($slot['status'] === 'maintenance' ? 'maintenance' : ($stateClass === 'slot-occupied' ? 'occupied' : 'available'))) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($by_row)): ?>
        <div class="parking-empty-state">
            <i class="bi bi-car-front"></i>
            <p>No slots configured</p>
            <p style="font-size: 0.85rem; color: #9ca3af; margin: 0;">Admin can add parking slots in Manage Slots</p>
        </div>
    <?php endif; ?>

<style>
    .parking-map-page {
        max-width: 100%;
        margin: 0;
        font-family: 'Google Sans Flex', sans-serif;
    }

    .parking-map-header {
        margin-bottom: 2rem;
        margin-top: 3rem;
        padding: 2rem;
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        border-radius: 16px;
        color: #fff;
        box-shadow: 0 8px 24px rgba(22, 163, 74, 0.15);
    }

    .parking-map-header h3 {
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

    .parking-map-header p {
        color: rgba(255, 255, 255, 0.95);
        font-size: 0.95rem;
        margin: 0;
    }

    .parking-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1.5rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        padding: 1rem;
        background: #f9fafb;
        border-radius: 12px;
    }

    .parking-legend {
        display: flex;
        gap: 1.5rem;
        flex-wrap: wrap;
        align-items: center;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 600;
        font-size: 0.9rem;
        color: #374151;
    }

    .legend-box {
        width: 24px;
        height: 24px;
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
    }

    .legend-available { background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); }
    .legend-occupied { background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%); }

    .parking-controls-right {
        display: flex;
        gap: 1rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .parking-controls-right label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin: 0;
        font-weight: 600;
        color: #6b7280;
        font-size: 0.9rem;
        cursor: pointer;
    }

    .parking-controls-right input[type="checkbox"] {
        cursor: pointer;
        width: 18px;
        height: 18px;
    }

    .parking-controls-right .btn {
        padding: 0.65rem 1.25rem;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        color: #fff;
        border: none;
        text-decoration: none;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
    }

    .parking-controls-right .btn:hover {
        background: linear-gradient(135deg, #15803d 0%, #166d31 100%);
        box-shadow: 0 6px 16px rgba(22, 163, 74, 0.4);
        transform: translateY(-2px);
    }

    .parking-grid {
        display: flex;
        flex-direction: column;
        gap: 2.5rem;
    }

    .parking-grid-row-label {
        font-size: 1.1rem;
        font-weight: 800;
        color: #111;
        margin-bottom: 1rem;
        letter-spacing: -0.5px;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .parking-grid-row-label::before {
        content: '';
        width: 4px;
        height: 24px;
        background: linear-gradient(180deg, #16a34a 0%, #15803d 100%);
        border-radius: 2px;
    }

    .parking-grid-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 1.25rem;
        padding: 0 0.5rem;
    }

    .slot-box {
        aspect-ratio: 1 / 1.15;
        border-radius: 14px;
        padding: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-start;
        font-size: 0.95rem;
        font-weight: 800;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        border: 2px solid transparent;
        overflow: hidden;
        position: relative;
    }

    .slot-box::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        opacity: 0;
        transition: opacity 0.2s;
        background: rgba(255, 255, 255, 0.1);
    }

    .slot-box:hover::before {
        opacity: 1;
    }

    .slot-box:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
    }

    .slot-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: 100%;
        padding: 1rem;
        position: relative;
        z-index: 1;
    }

    .slot-number {
        font-size: 1.35rem;
        font-weight: 900;
        letter-spacing: -0.5px;
        margin-bottom: 0.35rem;
    }

    .slot-vehicle-info {
        font-size: 0.65rem;
        font-weight: 600;
        line-height: 1.2;
        max-width: 100%;
        text-align: center;
        opacity: 0.85;
        margin-bottom: 0.35rem;
    }

    .slot-entry-time {
        font-size: 0.7rem;
        font-weight: 700;
        opacity: 0.75;
        margin-top: auto;
    }

    .slot-status {
        padding: 0.5rem 0;
        width: 100%;
        text-align: center;
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-top: 1px solid rgba(0, 0, 0, 0.1);
    }

    .slot-box.slot-available {
        background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
        color: #15803d;
        border-color: #86efac;
    }

    .slot-box.slot-available:hover {
        border-color: #15803d;
    }

    .slot-box.slot-occupied {
        background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%);
        color: #991b1b;
        border-color: #fca5a5;
    }

    .slot-box.slot-occupied:hover {
        border-color: #991b1b;
    }

    .parking-empty-state {
        text-align: center;
        padding: 3rem 2rem;
        background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
        border: 2px dashed #d1d5db;
        border-radius: 14px;
        color: #6b7280;
    }

    .parking-empty-state i {
        font-size: 4rem;
        color: #d1d5db;
        display: block;
        margin-bottom: 1rem;
    }

    .parking-empty-state p {
        font-size: 1rem;
        color: #374151;
        margin: 0.5rem 0;
    }

    @media (max-width: 768px) {
        .parking-map-header {
            padding: 1.5rem;
            margin: 1.5rem 0 2rem 0;
        }

        .parking-map-header h3 {
            font-size: 1.4rem;
        }

        .parking-controls {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start;
        }

        .parking-legend {
            width: 100%;
            gap: 1rem;
        }

        .parking-controls-right {
            width: 100%;
            justify-content: space-between;
        }

        .parking-grid-row {
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
        }

        .slot-box {
            aspect-ratio: 1 / 1.2;
        }

        .slot-number {
            font-size: 1.15rem;
        }

        .slot-content {
            padding: 0.85rem;
        }
    }

    @media (max-width: 480px) {
        .parking-map-page {
            padding: 0;
        }

        .parking-map-header {
            border-radius: 0;
            margin: 1rem 0 1.5rem 0;
        }

        .parking-map-header h3 {
            font-size: 1.25rem;
        }

        .parking-grid-row {
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
        }

        .slot-box {
            aspect-ratio: 1 / 1.1;
        }

        .slot-number {
            font-size: 1rem;
        }

        .slot-vehicle-info {
            font-size: 0.6rem;
        }

        .parking-legend {
            gap: 0.75rem;
        }

        .legend-item {
            gap: 0.5rem;
            font-size: 0.85rem;
        }
    }
</style>
<script>
(function() {
    var cb = document.getElementById('autoRefresh');
    if (cb) cb.addEventListener('change', function() {
        if (this.checked) window._parkRefresh = setInterval(function() { location.reload(); }, 30000);
        else { clearInterval(window._parkRefresh); window._parkRefresh = null; }
    });
})();
</script>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
