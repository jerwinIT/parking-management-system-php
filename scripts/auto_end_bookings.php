<?php
// Auto-end parked bookings script (cron-friendly)
// Usage (CLI): php scripts/auto_end_bookings.php [--dry-run]

define('PARKING_ACCESS', true);
require_once __DIR__ . '/../config/init.php';

$dry = in_array('--dry-run', $argv ?? []);
$pdo = getDB();

function logMsg($msg) {
    echo '[' . date('Y-m-d H:i:s') . "] " . $msg . "\n";
}

try {
    // Find parked bookings that have a planned duration and where entry_time + planned_duration <= NOW()
    $sel = $pdo->prepare(
        "SELECT b.id, b.parking_slot_id, TIMESTAMPDIFF(MINUTE, b.planned_entry_time, b.exit_time) AS planned_mins
         FROM bookings b
         WHERE b.status = 'parked' AND b.entry_time IS NOT NULL AND b.planned_entry_time IS NOT NULL
         AND DATE_ADD(b.entry_time, INTERVAL TIMESTAMPDIFF(MINUTE, b.planned_entry_time, b.exit_time) MINUTE) <= NOW()"
    );
    $sel->execute();
    $rows = $sel->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        logMsg('No bookings to auto-end.');
        exit(0);
    }

    logMsg('Found ' . count($rows) . ' parked booking(s) to auto-end.');
    foreach ($rows as $r) {
        logMsg(" -> booking_id={$r['id']} slot_id={$r['parking_slot_id']} planned_mins=" . intval($r['planned_mins']));
    }

    if ($dry) {
        logMsg('Dry-run mode, no database changes will be made.');
        exit(0);
    }

    $pdo->beginTransaction();
    $uBooking = $pdo->prepare("UPDATE bookings SET status = 'completed', exit_time = DATE_ADD(entry_time, INTERVAL ? MINUTE) WHERE id = ? AND status = 'parked'");
    $uSlot = $pdo->prepare("UPDATE parking_slots SET status = 'available' WHERE id = ?");

    $ended = 0;
    foreach ($rows as $r) {
        $mins = max(0, (int) ($r['planned_mins'] ?? 0));
        if ($mins <= 0) continue;
        $uBooking->execute([$mins, $r['id']]);
        if ($uBooking->rowCount() > 0) {
            $uSlot->execute([$r['parking_slot_id']]);
            $ended++;
            logMsg("   ended booking {$r['id']} (exit set to entry_time + {$mins} minute(s))");
        } else {
            logMsg("   skipped booking {$r['id']} (likely already ended/updated)");
        }
    }

    $pdo->commit();
    logMsg("Auto-ended {$ended} booking(s).");
    exit(0);

} catch (Exception $e) {
    try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Exception $_) {}
    logMsg('Error: ' . $e->getMessage());
    exit(1);
}
