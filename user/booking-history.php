<?php
/**
 * Booking History - List user's bookings with status (Pending, Parked, Completed)
 */
define('PARKING_ACCESS', true);
require_once dirname(__DIR__) . '/config/init.php';
requireLogin();
if (isAdmin()) { header('Location: ' . BASE_URL . '/index.php'); exit; }

$page_title = 'My Bookings';
$current_page = 'booking-history';

$pdo = getDB();
$stmt = $pdo->prepare("
        SELECT b.id, b.status, b.booked_at, b.entry_time, b.planned_entry_time, b.exit_time, v.plate_number, v.model, ps.slot_number,
            TIMESTAMPDIFF(MINUTE, COALESCE(b.entry_time, b.planned_entry_time, b.booked_at), COALESCE(b.exit_time, NOW())) AS duration_minutes,
           p.amount
    FROM bookings b
    JOIN vehicles v ON b.vehicle_id = v.id
    JOIN parking_slots ps ON b.parking_slot_id = ps.id
    LEFT JOIN payments p ON p.booking_id = b.id
    WHERE b.user_id = ? AND b.status IN ('pending', 'parked', 'completed', 'cancelled')
    ORDER BY b.booked_at DESC
");
$stmt->execute([currentUserId()]);
$bookings = $stmt->fetchAll();

// Calculate stats - separate active from completed/cancelled
$active_bookings = 0;
$past_bookings = 0;
foreach ($bookings as $b) {
    if (in_array($b['status'], ['pending', 'parked'])) {
        $active_bookings++;
    } else {
        $past_bookings++;
    }
}

// Format slot label
function slotLabel($slot_number) {
    if (preg_match('/^([A-Z])(\d+)$/', $slot_number, $m)) return $m[1] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT);
    return $slot_number;
}

require dirname(__DIR__) . '/includes/header.php';
?>

<style>
/* Modern My Bookings Styling */
* {
    font-family: 'Google Sans Flex', sans-serif;
}
.my-bookings-page {
    max-width: 100%;
    width: 100%;
    margin: 0;
    font-family: 'Google Sans Flex', sans-serif;
}
.bookings-header {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    color: #fff;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    box-shadow: 0 8px 24px rgba(22, 163, 74, 0.15);
}
.bookings-header h4 {
    font-size: 1.75rem;
    font-weight: 700;
    color: #fff;
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.bookings-header p {
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.95rem;
    margin: 0;
}
.summary-cards { display: none; }
.booking-history-header { font-size: 1.5rem; font-weight: 700; color: #111; margin-bottom: 1.5rem; margin-top: 1.5rem; }

.filter-tabs {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    background: #f9fafb;
    padding: 0.5rem;
    border-radius: 12px;
    border: 1px solid #e5f2e8;
}

.filter-tab {
    padding: 0.75rem 1.5rem;
    border: none;
    background: transparent;
    font-size: 0.95rem;
    font-weight: 600;
    color: #6b7280;
    cursor: pointer;
    position: relative;
    transition: all cubic-bezier(0.4, 0, 0.2, 1) 0.3s;
    border-radius: 8px;
}

.filter-tab:hover {
    background: #f0fdf4;
    color: #374151;
}
.filter-tab.active {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    color: #fff;
    box-shadow: 0 2px 8px rgba(22, 163, 74, 0.2);
}

.tab-badge {
    display: inline-block;
    background: rgba(255, 255, 255, 0.3);
    color: currentColor;
    border-radius: 999px;
    padding: 0.3rem 0.7rem;
    font-size: 0.75rem;
    font-weight: 700;
    margin-left: 0.5rem;
}

.booking-list { background: #fff; border: 1px solid #e5f2e8; border-radius: 14px; overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06), 0 4px 16px rgba(22, 163, 74, 0.08); }

.booking-row-header {
    display: grid;
    grid-template-columns: 80px 120px 180px 120px 80px 1fr;
    gap: 1.5rem;
    padding: 1.25rem 1.5rem;
    background: linear-gradient(135deg, #f0fdf4 0%, #fafcfb 100%);
    border-bottom: 1px solid #e5f2e8;
    font-weight: 700;
    font-size: 0.75rem;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    align-items: center;
}

.booking-row {
    display: grid;
    grid-template-columns: 80px 120px 180px 120px 80px 1fr;
    gap: 1.5rem;
    padding: 1.5rem;
    border-bottom: 1px solid #f0fdf4;
    align-items: center;
    transition: all cubic-bezier(0.4, 0, 0.2, 1) 0.3s;
    background: #fff;
}

.booking-row:hover {
    background: #f9fdfb;
    border-color: #e5f2e8;
}
.booking-row:last-child { border-bottom: none; }

.booking-slot-col { font-weight: 700; font-size: 1.1rem; background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }

.booking-date-col {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #374151;
    font-size: 0.9rem;
}

.booking-date-col i { color: #9ca3af; font-size: 1rem; }

.booking-time-col {
    font-size: 0.9rem;
    color: #374151;
}

.booking-time-col .time-text { font-weight: 600; }
.booking-time-col .duration-text { font-size: 0.8rem; color: #6b7280; margin-top: 0.25rem; }

.booking-status-col { text-align: left; }

.booking-status {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    white-space: nowrap;
    transition: all cubic-bezier(0.4, 0, 0.2, 1) 0.3s;
}

.booking-status.pending { background: linear-gradient(135deg, #fef3c7 0%, #fcd34d 100%); color: #92400e; box-shadow: 0 2px 8px rgba(217, 119, 6, 0.1); }
.booking-status.parked { background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); color: #166534; box-shadow: 0 2px 8px rgba(22, 163, 74, 0.1); }
.booking-status.completed { background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); color: #166534; box-shadow: 0 2px 8px rgba(22, 163, 74, 0.1); }
.booking-status.cancelled { background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #991b1b; box-shadow: 0 2px 8px rgba(220, 38, 38, 0.1); }

.booking-amount-col {
    font-weight: 700;
    font-size: 1rem;
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-align: right;
}

.booking-actions-col { display: flex; gap: 0.75rem; justify-content: flex-end; flex-wrap: wrap; }

.booking-actions-col a, .booking-actions-col button {
    padding: 0.65rem 1rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.8rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border: none;
    cursor: pointer;
    transition: all cubic-bezier(0.4, 0, 0.2, 1) 0.3s;
    white-space: nowrap;
}

.btn-end-parking { background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); color: #fff; box-shadow: 0 2px 8px rgba(22, 163, 74, 0.15); }
.btn-end-parking:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(22, 163, 74, 0.25); }
.btn-end-parking:active { transform: translateY(0); }

.btn-view-receipt { background: #f3f4f6; color: #374151; text-decoration: none; border: 1px solid #d1d5db; }
.btn-view-receipt:hover { background: #e5e7eb; color: #111; border-color: #9ca3af; }

.btn-edit { background: #f3f4f6; color: #374151; text-decoration: none; border: 1px solid #d1d5db; }
.btn-edit:hover { background: #e5e7eb; color: #111; border-color: #9ca3af; }

.btn-cancel-booking { background: #fee2e2; color: #b91c1c; text-decoration: none; border: 1px solid #fecaca; }
.btn-cancel-booking:hover { background: #fecaca; color: #7f1d1d; border-color: #f87171; }

@media (max-width: 1200px) {
    .booking-row-header,
    .booking-row {
        grid-template-columns: 70px 100px 140px 100px 70px 1fr;
        gap: 1rem;
        padding: 1rem;
    }
}

@media (max-width: 768px) {
    .booking-row-header { display: none; }
    
    .booking-row {
        grid-template-columns: 1fr;
        padding: 1.25rem;
        gap: 0.75rem;
        border: 1px solid #e5e7eb;
        margin-bottom: 1rem;
        border-radius: 8px;
    }
    
    .booking-row::before {
        content: attr(data-label);
        font-weight: 600;
        color: #6b7280;
        font-size: 0.75rem;
        text-transform: uppercase;
        display: none;
    }
    
    .booking-status-col,
    .booking-amount-col { text-align: left; }
}
</style>

<div class="my-bookings-page" style=" margin-bottom: 2rem;
        margin-top: 3rem;">

<div class="bookings-header">
    <h4><i class="bi bi-journal-text"></i>My Bookings</h4>
    <p>View and manage all your parking reservations</p>
</div>

<!-- Summary Cards -->
<div class="summary-cards">
    <div class="summary-card">
        <div class="summary-label">Active Bookings</div>
        <div class="summary-value active"><?= $active_bookings ?></div>
        <div class="summary-desc">Pending or currently parked</div>
    </div>
    <div class="summary-card">
        <div class="summary-label">Total Bookings</div>
        <div class="summary-value" style="color: #3b82f6;"><?= $active_bookings ?></div>
        <div class="summary-desc">Active bookings this session</div>
    </div>
</div>

<!-- Booking History with Tabs -->
<h2 class="booking-history-header">Booking History</h2>

<?php if (!empty($bookings)): ?>
    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <button class="filter-tab active" data-filter="all">
            All
            <span class="tab-badge"><?= count($bookings) ?></span>
        </button>
        <button class="filter-tab" data-filter="active">
            Active
            <span class="tab-badge"><?= $active_bookings ?></span>
        </button>
        <button class="filter-tab" data-filter="past">
            Past
            <span class="tab-badge"><?= $past_bookings ?></span>
        </button>
    </div>
<?php endif; ?>

<?php if (empty($bookings)): ?>
    <div style="text-align: center; padding: 3rem 1rem; color: #6b7280;">
        <i class="bi bi-car-front" style="font-size: 3rem; color: #d1d5db; display: block; margin-bottom: 1rem;"></i>
        <p style="font-size: 1rem; margin: 0.5rem 0;">No active bookings.</p>
        <p style="font-size: 0.9rem; margin: 0;"><a href="<?= BASE_URL ?>/user/book.php" style="color: #16a34a; text-decoration: none;">Book a slot</a> to get started.</p>
    </div>
<?php else: ?>
    <div class="booking-list">
        <div class="booking-row-header">
            <div>Slot</div>
            <div>Date</div>
            <div>Time</div>
            <div>Status</div>
            <div>Amount</div>
            <div></div>
        </div>
        
        <?php foreach ($bookings as $b): ?>
            <?php
            $slot_label = slotLabel($b['slot_number']);
            // Use entry_time, otherwise planned_entry_time, otherwise booked_at for display and duration
            $entry_source = $b['entry_time'] ?? $b['planned_entry_time'] ?? $b['booked_at'] ?? null;
            $booking_date = $entry_source ? date('m/d/Y', strtotime($entry_source)) : '—';
            $start_time = $entry_source ? date('g:i A', strtotime($entry_source)) : '—';
            $end_time = $b['exit_time'] ? date('g:i A', strtotime($b['exit_time'])) : '—';
            $time_range = $start_time . ' - ' . $end_time;
            $mins = (int) ($b['duration_minutes'] ?? 0);
            $duration = '';
            if ($mins >= 60) {
                $duration = floor($mins/60) . 'h ' . ($mins%60) . 'm';
            } else if ($mins > 0) {
                $duration = $mins . 'm';
            }
            $amount = isset($b['amount']) && $b['amount'] !== null ? number_format((float)$b['amount'], 2) : '0.00';
            $status_class = strtolower($b['status']);
            $booking_type = in_array($b['status'], ['pending', 'parked']) ? 'active' : 'past';
            ?>
            <div class="booking-row" data-status="<?= htmlspecialchars($status_class) ?>" data-type="<?= $booking_type ?>">
                <div class="booking-slot-col"><?= htmlspecialchars($slot_label) ?></div>
                
                <div class="booking-date-col">
                    <i class="bi bi-calendar3"></i>
                    <span><?= htmlspecialchars($booking_date) ?></span>
                </div>
                
                <div class="booking-time-col">
                    <div class="time-text"><i class="bi bi-clock me-2" style="color: #9ca3af;"></i><?= htmlspecialchars($time_range) ?></div>
                    <?php if ($duration): ?>
                        <div class="duration-text"><?= htmlspecialchars($duration) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="booking-status-col">
                    <span class="booking-status <?= $status_class ?>"><?= ucfirst($b['status']) ?></span>
                </div>
                
                <div class="booking-amount-col">&#8369;<?= htmlspecialchars($amount) ?></div>
                
                <div class="booking-actions-col">
                    <?php if ($b['status'] === 'parked'): ?>
                        <button type="button" class="btn-end-parking btn-end-parking-trigger" data-id="<?= (int)$b['id'] ?>">
                            End Parking
                        </button>
                    <?php elseif ($b['status'] === 'pending'): ?>
                        <a href="<?= BASE_URL ?>/user/booking-details.php?id=<?= (int)$b['id'] ?>" class="btn-view-receipt">View</a>
                        <a href="<?= BASE_URL ?>/user/cancel-booking.php?id=<?= (int)$b['id'] ?>" class="btn-cancel-booking" onclick="return confirm('Cancel this booking?');">Cancel</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
</div>

<script>
// Filter tabs functionality
document.querySelectorAll('.filter-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        const filter = this.getAttribute('data-filter');
        
        // Update active tab
        document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        // Filter rows
        document.querySelectorAll('.booking-row').forEach(row => {
            const type = row.getAttribute('data-type');
            
            if (filter === 'all') {
                row.style.display = 'grid';
            } else if (filter === 'active' && type === 'active') {
                row.style.display = 'grid';
            } else if (filter === 'past' && type === 'past') {
                row.style.display = 'grid';
            } else {
                row.style.display = 'none';
            }
        });
    });
});
</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

<!-- End Parking Confirmation Modal -->
<div class="modal fade" id="endParkingModal" tabindex="-1" aria-labelledby="endParkingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="endParkingModalLabel">End Parking Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to end this parking session? This will record your exit time and free the slot.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmEndParkingBtn" class="btn btn-end-parking">End Parking</a>
            </div>
        </div>
    </div>
</div>

<script>
// End parking modal handling
document.addEventListener('DOMContentLoaded', function() {
        const triggers = document.querySelectorAll('.btn-end-parking-trigger');
        const confirmBtn = document.getElementById('confirmEndParkingBtn');
        const modalEl = document.getElementById('endParkingModal');
        if (!modalEl) return;
        const modal = new bootstrap.Modal(modalEl);

        triggers.forEach(btn => {
                btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const id = this.getAttribute('data-id');
                        confirmBtn.setAttribute('href', '<?= BASE_URL ?>/user/end-parking.php?id=' + encodeURIComponent(id));
                        modal.show();
                });
        });
});
</script>
