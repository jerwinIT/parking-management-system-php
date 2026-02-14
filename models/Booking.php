<?php
/**
 * Booking Model - Bookings data access and creation
 */
class Booking
{
    private const HOURLY_RATE_PHP = 30;

    /**
     * Get confirmation data for a booking (slot, times, amount)
     */
    public static function getConfirmationById(PDO $pdo, int $booking_id, int $user_id): ?array
    {
        $stmt = $pdo->prepare('
            SELECT b.id, b.entry_time, b.exit_time, b.parking_slot_id, ps.slot_number, p.amount
            FROM bookings b
            JOIN parking_slots ps ON ps.id = b.parking_slot_id
            LEFT JOIN payments p ON p.booking_id = b.id
            WHERE b.id = ? AND b.user_id = ?
        ');
        $stmt->execute([$booking_id, $user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Create a new booking and payment; mark slot occupied. Returns booking id or null on failure.
     */
    public static function create(PDO $pdo, int $user_id, int $vehicle_id, int $slot_id, ?string $entry_dt, ?string $exit_dt): ?int
    {
        // Calculate amount based on entry/exit times (bill in 15-minute increments, minimum 15 mins)
        $calc_entry_dt = $entry_dt ?: date('Y-m-d H:i:s');
        $amount = (float) self::HOURLY_RATE_PHP * 0.25; // minimum charge for 15 minutes
        if ($exit_dt) {
            $mins = max(0, (int) ((strtotime($exit_dt) - strtotime($calc_entry_dt)) / 60));
            $min_increment = 15;
            $billed_mins = max($min_increment, (int) (ceil($mins / $min_increment) * $min_increment));
            $amount = round(($billed_mins / 60) * self::HOURLY_RATE_PHP, 2);
        }

        $pdo->beginTransaction();
        try {
            // Bookings always start as 'pending'; admin must mark as 'parked' when vehicle arrives
            $pdo->prepare('UPDATE parking_slots SET status = ? WHERE id = ?')->execute(['pending', $slot_id]);
            $pdo->prepare('INSERT INTO bookings (user_id, vehicle_id, parking_slot_id, status, booked_at, planned_entry_time, entry_time, exit_time) VALUES (?, ?, ?, ?, NOW(), ?, NULL, ?)')
                ->execute([$user_id, $vehicle_id, $slot_id, 'pending', $entry_dt, $exit_dt]);
            $bid = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO payments (booking_id, amount, payment_status) VALUES (?, ?, ?)')
                ->execute([$bid, $amount, 'pending']);
            $pdo->commit();
            return $bid;
        } catch (Exception $e) {
            $pdo->rollBack();
            return null;
        }
    }

    /**
     * Build entry/exit datetime from date and time strings
     */
    public static function parseEntryExit(?string $date, ?string $start_time, ?string $end_time): array
    {
        $entry = null;
        $exit = null;
        if ($date && $start_time) {
            $t = strlen($start_time) === 5 ? $start_time . ':00' : substr($start_time, 0, 8);
            $entry = $date . ' ' . $t;
        }
        if ($date && $end_time) {
            $t = strlen($end_time) === 5 ? $end_time . ':00' : substr($end_time, 0, 8);
            $exit = $date . ' ' . $t;
        }
        return [$entry, $exit];
    }
}
