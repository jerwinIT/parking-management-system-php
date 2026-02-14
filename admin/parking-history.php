<?php
/**
 * Admin - Parking History (filter by date)
 */
define('PARKING_ACCESS', true);
require_once dirname(__DIR__) . '/config/init.php';
requireAdmin();
// ensure any PHP opcode cache is cleared so updated code runs (helpful during development)
if (function_exists('opcache_reset')) { @opcache_reset(); }

$page_title = 'Parking History';
$current_page = 'admin-history';
$pdo = getDB();

// Handle bulk delete booking request
$deleteMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_selected'])) {
    $selectedIds = $_POST['selected_bookings'] ?? [];
    if (!empty($selectedIds)) {
        try {
            $bookingIds = array_filter(array_map('intval', $selectedIds));
            if (!empty($bookingIds)) {
                $placeholders = implode(',', array_fill(0, count($bookingIds), '?'));
                $stmt = $pdo->prepare("DELETE FROM bookings WHERE id IN ($placeholders)");
                $stmt->execute($bookingIds);
                $deleted_count = $stmt->rowCount();
                setAlert($deleted_count . ' booking record(s) deleted successfully.', 'success');
                header('Location: ' . BASE_URL . '/admin/parking-history.php');
                exit;
            }
        } catch (Exception $e) {
            setAlert('Error deleting bookings: ' . $e->getMessage(), 'danger');
            header('Location: ' . BASE_URL . '/admin/parking-history.php');
            exit;
        }
    }
}

$date = $_GET['date'] ?? 'all';
$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? 'completed');  // Default to showing completed bookings
$month = trim($_GET['month'] ?? '');
$sort = ($_GET['sort'] ?? 'new') === 'old' ? 'old' : 'new';

$where = [];
$params = [];
if ($date !== 'all' && $date !== '') {
    $where[] = "(DATE(b.entry_time) = :date1 OR DATE(b.exit_time) = :date2)";
    $params['date1'] = $date;
    $params['date2'] = $date;
}
if ($status !== '' && $status !== 'active' && $status !== 'completed') {
    $where[] = 'b.status = :status';
    $params['status'] = $status;
} elseif ($status === 'active') {
    $where[] = "b.status IN ('pending', 'parked')";
} elseif ($status === 'completed') {
    $where[] = "b.status = 'completed'";
}
if ($month !== '') {
    $where[] = "(DATE_FORMAT(b.entry_time,'%Y-%m') = :month1 OR DATE_FORMAT(b.exit_time,'%Y-%m') = :month2)";
    $params['month1'] = $month;
    $params['month2'] = $month;
}
if ($q !== '') {
    $where[] = '(v.plate_number LIKE :q1 OR v.model LIKE :q2 OR s.slot_number LIKE :q3 OR u.full_name LIKE :q4)';
    $params['q1'] = '%' . $q . '%';
    $params['q2'] = '%' . $q . '%';
    $params['q3'] = '%' . $q . '%';
    $params['q4'] = '%' . $q . '%';
}

$orderSql = $sort === 'old' ? 'b.entry_time ASC' : 'b.entry_time DESC';

// Base joins
$joinSql = "FROM bookings b
    LEFT JOIN vehicles v ON v.id = b.vehicle_id
    LEFT JOIN parking_slots s ON s.id = b.parking_slot_id
    LEFT JOIN payments p ON p.booking_id = b.id
    LEFT JOIN users u ON u.id = b.user_id";

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

// Aggregates for stats on all filtered records
$aggSql = "SELECT COUNT(b.id) AS total_sessions, COALESCE(SUM(p.amount),0) AS total_spent, 
    COALESCE(SUM(TIMESTAMPDIFF(MINUTE, b.entry_time, b.exit_time)), 0) AS total_minutes,
    SUM(b.entry_time IS NOT NULL AND b.exit_time IS NOT NULL) AS duration_count
    " . $joinSql . ' ' . $whereSql;
$aggStmt = $pdo->prepare($aggSql);
foreach ($params as $k => $v) {
    $aggStmt->bindValue(':' . $k, $v);
}
$aggStmt->execute();
$agg = $aggStmt->fetch(PDO::FETCH_ASSOC);
$total_sessions = (int) ($agg['total_sessions'] ?? 0);
$total_spent = (float) ($agg['total_spent'] ?? 0.0);
$total_minutes = (int) ($agg['total_minutes'] ?? 0);
$duration_count = (int) ($agg['duration_count'] ?? 0);
$avg_duration_hours = $duration_count ? round(($total_minutes / $duration_count) / 60, 1) : 0;
$avg_cost = $total_sessions ? round($total_spent / $total_sessions, 2) : 0.00;

// Pagination
$perPage = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// total records for pagination
$countSql = 'SELECT COUNT(b.id) AS cnt ' . $joinSql . ' ' . $whereSql;
$countStmt = $pdo->prepare($countSql);
foreach ($params as $k => $v) {
    $countStmt->bindValue(':' . $k, $v);
}
$countStmt->execute();
$total_records = (int) $countStmt->fetchColumn();

// fetch paginated rows
$sql = "SELECT b.id, b.entry_time, b.exit_time, b.status, p.amount AS amount,
    v.plate_number, v.model AS model, s.slot_number, u.full_name " . $joinSql . ' ' . $whereSql . " ORDER BY " . $orderSql . " LIMIT :lim OFFSET :off";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v);
}
$stmt->bindValue(':lim', (int)$perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// export file if requested (CSV or Excel-like .xls via CSV content)
if (isset($_GET['export']) && in_array($_GET['export'], ['csv','xls','excel'])) {
    $exportType = $_GET['export'];
    $exportSql = "SELECT b.id, b.entry_time, b.exit_time, b.status, p.amount AS amount,
        v.plate_number, v.model AS model, s.slot_number, u.full_name " . $joinSql . ' ' . $whereSql . " ORDER BY " . $orderSql;
    $exportStmt = $pdo->prepare($exportSql);
    foreach ($params as $k => $v) {
        $exportStmt->bindValue(':' . $k, $v);
    }
    $exportStmt->execute();
    $exportRows = $exportStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($exportType === 'csv') {
        header('Content-Type: text/csv');
        $ext = 'csv';
    } else {
        // Excel-compatible CSV (.xls) - many clients will open this in Excel
        header('Content-Type: application/vnd.ms-excel');
        $ext = 'xls';
    }
    header('Content-Disposition: attachment; filename="parking_history_' . date('Ymd') . '.'. $ext . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Entry Time','Exit Time','Status','Slot','Vehicle','User','Amount']);
    foreach ($exportRows as $r) {
        fputcsv($out, [
            $r['id'] ?? '',
            $r['entry_time'] ?? '',
            $r['exit_time'] ?? '',
            $r['status'] ?? '',
            $r['slot_number'] ?? '',
            ($r['plate_number'] ?? '') . (!empty($r['model']) ? ' - '.$r['model'] : ''),
            $r['full_name'] ?? '',
            isset($r['amount']) ? number_format((float)$r['amount'], 2) : '0.00'
        ]);
    }
    fclose($out);
    exit;
}

require dirname(__DIR__) . '/includes/header.php';
?>

<style>
/* Modern Admin Parking History Styling */
* {
    font-family: 'Google Sans Flex', sans-serif;
}
.parking-history-page {
    width: 100%;
    max-width: 100%;
    padding: 0;
    margin: 0;
}

.ph-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 2rem;
    margin-top: 1rem;
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    padding: 2rem;
    border-radius: 16px;
    color: #fff;
    box-shadow: 0 8px 24px rgba(22, 163, 74, 0.15);
}

.ph-header-content h2 {
    font-size: 1.75rem;
    font-weight: 800;
    color: #fff;
    margin: 0 0 0.5rem 0;
    letter-spacing: -0.5px;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.ph-header-content p {
    color: rgba(255, 255, 255, 0.95);
    font-size: 0.95rem;
    margin: 0;
}

.ph-header .btn-group .dropdown-toggle {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: #fff;
    font-weight: 700;
    transition: all 0.2s ease;
}

.ph-header .btn-group .dropdown-toggle:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-2px);
}

.ph-stats {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}

.ph-card {
    flex: 1;
    min-width: 200px;
    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
    border: 1px solid #86efac;
    border-radius: 14px;
    padding: 1.5rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.ph-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.ph-card .label {
    font-size: 0.75rem;
    font-weight: 700;
    color: #166534;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
}

.ph-card .value {
    font-size: 2rem;
    font-weight: 800;
    color: #16a34a;
    letter-spacing: -1px;
    margin-bottom: 0.5rem;
}

.ph-card .text-muted {
    font-size: 0.85rem;
    color: #6b7280;
}

.ph-card.spent {
    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
    border-color: #86efac;
}

.ph-card.spent .label {
    color: #166534;
}

.ph-card.spent .value {
    color: #16a34a;
}

.ph-card.blue {
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    border-color: #93c5fd;
}

.ph-card.blue .label {
    color: #0c4a6e;
}

.ph-card.blue .value {
    color: #2563eb;
}

.ph-card.purple {
    background: linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%);
    border-color: #d8b4fe;
}

.ph-card.purple .label {
    color: #5b21b6;
}

.ph-card.purple .value {
    color: #7c3aed;
}

.filters {
    background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
    padding: 1.5rem;
    border-radius: 14px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    margin-bottom: 2rem;
}

.filters form {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: center;
}

.filters .form-control,
.filters .form-select {
    border-radius: 10px;
    border: 1px solid #e5e7eb;
    background: #fff;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-weight: 500;
}

.filters .form-control:focus,
.filters .form-select:focus {
    border-color: #16a34a;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
}

.filters .btn {
    border-radius: 10px;
    font-weight: 700;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    padding: 0.5rem 1.25rem;
}

.filters .btn-primary {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    border: none;
    box-shadow: 0 2px 8px rgba(22, 163, 74, 0.15);
    color: #fff;
}

.filters .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(22, 163, 74, 0.25);
}

.filters .btn-outline-secondary {
    border: 1.5px solid #d1d5db;
    color: #374151;
    background: #fff;
}

.filters .btn-outline-secondary:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
    color: #111;
    transform: translateY(-2px);
}

.slot-badge {
    display: inline-block;
    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
    color: #166534;
    padding: 0.5rem 0.85rem;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.85rem;
    box-shadow: 0 2px 8px rgba(22, 163, 74, 0.1);
    border: 1px solid #86efac;
}

.card {
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
    border-color: #16a34a;
}

.parking-history-table {
    table-layout: auto;
    width: 100%;
}

.parking-history-table thead {
    background: linear-gradient(135deg, #f0fdf4 0%, #fafcfb 100%);
}

.parking-history-table thead th {
    color: #6b7280;
    border-bottom: 1px solid #e5e7eb;
    font-weight: 700;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 1rem 1.25rem;
}

.parking-history-table tbody tr {
    border-bottom: 1px solid #f3f4f6;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.parking-history-table tbody tr:hover {
    background: #f9fdfb;
}

.parking-history-table tbody tr:last-child {
    border-bottom: none;
}

.parking-history-table td {
    vertical-align: middle;
    padding: 1rem 1.25rem;
    color: #374151;
    font-size: 0.9rem;
}

.ph-vehicle-name {
    font-weight: 700;
    color: #111827;
    font-size: 0.95rem;
}

.ph-plate {
    color: #9ca3af;
    margin-top: 0.25rem;
    font-size: 0.85rem;
}

.text-success {
    color: #16a34a !important;
    font-weight: 700;
}

.pagination {
    margin-top: 2rem;
    justify-content: center;
}

.pagination .page-link {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    color: #374151;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    margin: 0 0.35rem;
    font-weight: 600;
}

.pagination .page-link:hover {
    background: #f0fdf4;
    color: #16a34a;
    border-color: #16a34a;
}

.pagination .page-item.active .page-link {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    border-color: #16a34a;
    color: #fff;
}

@media (max-width: 768px) {
    .ph-header {
        flex-direction: column;
        gap: 1rem;
    }

    .ph-stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .filters form {
        flex-direction: column;
    }

    .filters .form-control,
    .filters .form-select,
    .filters .btn {
        width: 100%;
    }
}
</style>

    <div class="parking-history-page">
        <div class="ph-header" style=" margin-top: 3rem;">
            <div class="ph-header-content">
                <h2>Parking History</h2>
                <p>Complete record of all parking sessions</p>
            </div>
            <div>
                <?php $exportParams = $_GET; unset($exportParams['page']); ?>
                <div class="btn-group">
                    <button type="button" class="btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-download me-1"></i> Export</button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php $ep = $exportParams; $ep['export'] = 'csv'; ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/parking-history.php?<?= http_build_query($ep) ?>">Download CSV</a></li>
                        <?php $ep2 = $exportParams; $ep2['export'] = 'xls'; ?>
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/parking-history.php?<?= http_build_query($ep2) ?>">Download Excel</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="ph-stats">
            <div class="ph-card">
                <div class="label">Total Sessions</div>
                <div class="value"><?= $total_sessions ?></div>
                <div class="text-muted">Parking sessions recorded</div>
            </div>
            <div class="ph-card spent">
                <div class="label">Total Spent</div>
                <div class="value">&#8369;<?= number_format($total_spent, 2) ?></div>
                <div class="text-muted">All parking fees</div>
            </div>
            <div class="ph-card purple">
                <div class="label">Avg Cost</div>
                <div class="value">&#8369;<?= $total_sessions ? number_format($avg_cost, 2) : '—' ?></div>
                <div class="text-muted">Per session</div>
            </div>
        </div>

        <div class="filters">
            <form method="get" action="<?= BASE_URL ?>/admin/parking-history.php">
                <input type="search" name="q" class="form-control" placeholder="Search slot, vehicle, plate..." value="<?= htmlspecialchars($q) ?>" style="flex: 1;">
                <select name="status" class="form-select" style="width:160px;">
                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active Only</option>
                    <option value="" <?= $status === '' ? 'selected' : '' ?>>All Status</option>
                    <option value="parked" <?= $status === 'parked' ? 'selected' : '' ?>>Parked</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                </select>
                <input type="month" name="month" class="form-control" style="width:170px;" value="<?= htmlspecialchars($month) ?>">
                <select name="sort" class="form-select" style="width:170px;">
                    <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>Newest First</option>
                    <option value="old" <?= $sort === 'old' ? 'selected' : '' ?>>Oldest First</option>
                </select>
                <input type="date" name="date" class="form-control" value="<?= $date === 'all' ? '' : htmlspecialchars($date) ?>" style="width:170px;">
                <button class="btn btn-primary">Filter</button>
                <a href="<?= BASE_URL ?>/admin/parking-history.php?date=all" class="btn btn-outline-secondary">Show All</a>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-3">
            <form method="POST" id="bulkDeleteForm" style="margin-bottom:0;">
                <div class="d-flex align-items-center gap-2">
                    <button type="submit" name="delete_selected" class="btn btn-danger btn-sm" id="deleteSelectedBtn" style="display:none;">
                        <i class="bi bi-trash me-1"></i> Delete Selected
                    </button>
                    <span id="selectedCount" class="text-muted" style="font-size:0.9rem;"></span>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle parking-history-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px"><input type="checkbox" id="selectAllCheckbox" class="form-check-input"></th>
                            <th style="width:120px">Date</th>
                            <th style="width:90px">Slot</th>
                            <th>Vehicle</th>
                            <th style="width:140px">Time</th>
                            <th style="width:110px">Duration</th>
                            <th style="width:120px" class="text-end">Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r):
                                // Date label
                                $dateLabel = !empty($r['entry_time']) ? date('Y-m-d', strtotime($r['entry_time'])) : 
                                             (!empty($r['exit_time']) ? date('Y-m-d', strtotime($r['exit_time'])) : '—');
                                
                                // Initialize defaults
                                $timeLabel = '—';
                                $durationLabel = '—';
                                
                                // Calculate time and duration
                                if (!empty($r['entry_time'])) {
                                    $entryTime = strtotime($r['entry_time']);
                                    
                                    if (!empty($r['exit_time'])) {
                                        $exitTime = strtotime($r['exit_time']);
                                        
                                        // FIXED: Ensure exit is after entry
                                        if ($exitTime >= $entryTime) {
                                            // Display time range
                                            $timeLabel = date('g:i A', $entryTime) . ' - ' . date('g:i A', $exitTime);
                                            
                                            // FIXED: Calculate duration correctly
                                            $totalSeconds = $exitTime - $entryTime;
                                            $totalMinutes = floor($totalSeconds / 60);
                                            
                                            // Convert to hours and minutes
                                            $hours = floor($totalMinutes / 60);
                                            $minutes = $totalMinutes % 60;
                                            
                                            // Format duration label
                                            if ($hours > 0 && $minutes > 0) {
                                                $durationLabel = $hours . 'h ' . $minutes . 'm';
                                            } elseif ($hours > 0) {
                                                $durationLabel = $hours . 'h';
                                            } elseif ($minutes > 0) {
                                                $durationLabel = $minutes . 'm';
                                            } else {
                                                $durationLabel = '0m';
                                            }
                                        } else {
                                            // Exit time is before entry time (data error)
                                            $timeLabel = date('g:i A', $entryTime) . ' - ' . date('g:i A', $exitTime) . ' (ERROR)';
                                            $durationLabel = 'Invalid';
                                        }
                                    } else {
                                        // No exit time yet (still parked or pending)
                                        $timeLabel = date('g:i A', $entryTime) . ' - Ongoing';
                                        $durationLabel = '—';
                                    }
                                } elseif (!empty($r['exit_time'])) {
                                    // Only exit time (unusual case)
                                    $timeLabel = '— - ' . date('g:i A', strtotime($r['exit_time']));
                                    $durationLabel = '—';
                                }
                                
                                $cost = isset($r['amount']) ? number_format((float)$r['amount'], 2) : '0.00';
                            ?>
                                <tr>
                                    <td><input type="checkbox" class="form-check-input booking-checkbox" value="<?= $r['id'] ?>"></td>
                                    <td><?= htmlspecialchars($dateLabel) ?></td>
                                    <td><span class="slot-badge"><?= htmlspecialchars($r['slot_number'] ?? '—') ?></span></td>
                                    <td>
                                        <?php if (!empty($r['model'])): ?>
                                            <div class="ph-vehicle-name"><?= htmlspecialchars($r['model']) ?></div>
                                            <div class="ph-plate"><?= htmlspecialchars($r['plate_number'] ?? '—') ?></div>
                                        <?php else: ?>
                                            <div class="ph-vehicle-name"><?= htmlspecialchars($r['plate_number'] ?? '—') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $timeLabel ?></td>
                                    <td class="fw-bold"><?= $durationLabel ?></td>
                                    <td class="text-end text-success fw-bold">&#8369;<?= $cost ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php if ($total_records > $perPage): ?>
    <nav class="mt-3" aria-label="Page navigation">
        <ul class="pagination">
            <?php
            $total_pages = (int) ceil($total_records / $perPage);
            $queryParams = $_GET;
            unset($queryParams['page']); unset($queryParams['export']);
            $start = max(1, $page - 3);
            $end = min($total_pages, $page + 3);
            ?>
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <?php $queryParams['page'] = $page - 1; ?>
                <a class="page-link" href="<?= BASE_URL ?>/admin/parking-history.php?<?= http_build_query($queryParams) ?>">Previous</a>
            </li>
            <?php for ($p = $start; $p <= $end; $p++): $queryParams['page'] = $p; ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="<?= BASE_URL ?>/admin/parking-history.php?<?= http_build_query($queryParams) ?>"><?= $p ?></a></li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <?php $queryParams['page'] = $page + 1; ?>
                <a class="page-link" href="<?= BASE_URL ?>/admin/parking-history.php?<?= http_build_query($queryParams) ?>">Next</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const bookingCheckboxes = document.querySelectorAll('.booking-checkbox');
    const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
    const selectedCount = document.getElementById('selectedCount');
    const bulkDeleteForm = document.getElementById('bulkDeleteForm');

    function updateButtonState() {
        const checkedCount = document.querySelectorAll('.booking-checkbox:checked').length;
        if (checkedCount > 0) {
            deleteSelectedBtn.style.display = 'inline-block';
            selectedCount.textContent = checkedCount + ' record' + (checkedCount > 1 ? 's' : '') + ' selected';
        } else {
            deleteSelectedBtn.style.display = 'none';
            selectedCount.textContent = '';
        }
    }

    // Select all checkbox
    selectAllCheckbox.addEventListener('change', function() {
        bookingCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateButtonState();
    });

    // Individual checkboxes
    bookingCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const allChecked = Array.from(bookingCheckboxes).every(cb => cb.checked);
            const anyChecked = Array.from(bookingCheckboxes).some(cb => cb.checked);
            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = anyChecked && !allChecked;
            updateButtonState();
        });
    });

    // Handle form submission
    bulkDeleteForm.addEventListener('submit', function(e) {
        const checkedCount = document.querySelectorAll('.booking-checkbox:checked').length;
        if (checkedCount === 0) {
            e.preventDefault();
            alert('Please select at least one booking to delete.');
            return;
        }
        if (!confirm('Are you sure you want to delete ' + checkedCount + ' booking record(s)? This action cannot be undone.')) {
            e.preventDefault();
            return;
        }
        // Add selected checkboxes to form
        const selectedIds = Array.from(document.querySelectorAll('.booking-checkbox:checked')).map(cb => cb.value);
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_bookings[]';
        selectedIds.forEach(id => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'selected_bookings[]';
            hiddenInput.value = id;
            bulkDeleteForm.appendChild(hiddenInput);
        });
    });
});
</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>