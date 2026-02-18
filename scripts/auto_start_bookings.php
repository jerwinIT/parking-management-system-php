<?php
// Auto-start bookings script (cron-friendly)
// Usage (CLI): php scripts/auto_start_bookings.php [--dry-run]

define('PARKING_ACCESS', true);
require_once __DIR__ . '/../config/init.php';

$dry = in_array('--dry-run', $argv ?? []);
$pdo = getDB();

function logMsg($msg) {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
}

try {
    // Find pending/confirmed bookings that should start now or earlier
    $sel = $pdo->prepare(
        "SELECT id, parking_slot_id FROM bookings WHERE planned_entry_time IS NOT NULL AND planned_entry_time <= NOW() AND status IN ('pending', 'confirmed')"
    );
    $sel->execute();
    $rows = $sel->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        logMsg('No bookings to auto-start.');
        exit(0);
    }

    logMsg('Found ' . count($rows) . ' booking(s) to auto-start.');
    foreach ($rows as $r) {
        logMsg(" -> booking_id={$r['id']} slot_id={$r['parking_slot_id']}");
    }

    if ($dry) {
        logMsg('Dry-run mode, no database changes will be made.');
        exit(0);
    }

    $pdo->beginTransaction();
    $uBooking = $pdo->prepare("UPDATE bookings SET status = 'parked', entry_time = NOW() WHERE id = ? AND status IN ('pending', 'confirmed')");
    $uSlot = $pdo->prepare("UPDATE parking_slots SET status = 'occupied' WHERE id = ?");

    $started = 0;
    foreach ($rows as $r) {
        $uBooking->execute([$r['id']]);
        if ($uBooking->rowCount() > 0) {
            // Optionally mark slot occupied for backward compatibility
            $uSlot->execute([$r['parking_slot_id']]);
            $started++;
            logMsg("   started booking {$r['id']} (slot {$r['parking_slot_id']})");
        } else {
            logMsg("   skipped booking {$r['id']} (likely already started)");
        }
    }

    $pdo->commit();
    logMsg("Auto-started {$started} booking(s).");
    exit(0);

} catch (Exception $e) {
    try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Exception $_) {}
    logMsg('Error: ' . $e->getMessage());
    exit(1);
}
