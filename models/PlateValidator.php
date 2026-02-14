<?php
/**
 * PlateValidator
 * Helper functions for vehicle plate validation and normalization
 */
class PlateValidator
{
    // Vehicle types
    const TYPE_PRIVATE = 'private';
    const TYPE_MOTORCYCLE = 'motorcycle';
    const TYPE_GOVERNMENT = 'government';
    const TYPE_FOR_HIRE = 'for-hire';
    const TYPE_ELECTRIC = 'electric';
    const TYPE_CONDUCTION = 'conduction';

    // Validation patterns (PCRE) with comments
    // TYPE 1 - Private Vehicle: 3 letters (exclude I,O,Q) + 3-4 digits
    // Pattern: [A-HJ-NP-Z]{3}\s?[0-9]{3,4}
    public static $patterns = [
        self::TYPE_PRIVATE => '/^[A-HJ-NP-Z]{3}\s?[0-9]{3,4}$/i',

        // TYPE 2 - Motorcycle/Tricycle: 2 letters + 4-5 digits
        // Pattern: [A-Z]{2}[-\s]?[0-9]{4,5}
        self::TYPE_MOTORCYCLE => '/^[A-Z]{2}[-\s]?[0-9]{4,5}$/i',

        // TYPE 3 - Government Vehicle: 2-3 letters + 1-4 digits
        // Pattern: [A-Z]{2,3}\s?[0-9]{1,4}
        self::TYPE_GOVERNMENT => '/^[A-Z]{2,3}\s?[0-9]{1,4}$/i',

        // TYPE 4 - For-Hire Vehicle: same as private (3 letters excl. I,O,Q + 3-4 digits)
        self::TYPE_FOR_HIRE => '/^[A-HJ-NP-Z]{3}\s?[0-9]{3,4}$/i',

        // TYPE 5 - Electric Vehicle: starts with E then 3 letters (exclude I,O,Q) + 3-4 digits
        // Pattern: E\s?[A-HJ-NP-Z]{3}\s?[0-9]{3,4}
        self::TYPE_ELECTRIC => '/^E\s?[A-HJ-NP-Z]{3}\s?[0-9]{3,4}$/i',

        // TYPE 6 - Conduction Sticker: 7-8 digits only
        // Pattern: [0-9]{7,8}
        self::TYPE_CONDUCTION => '/^[0-9]{7,8}$/'
    ];

    // Example hints for UI
    public static $examples = [
        self::TYPE_PRIVATE => 'ABC1234 (or ABC123)',
        self::TYPE_MOTORCYCLE => 'MC12345 or TR-5678',
        self::TYPE_GOVERNMENT => 'SEN123 or GOV1234',
        self::TYPE_FOR_HIRE => 'TXI1234',
        self::TYPE_ELECTRIC => 'EABC1234',
        self::TYPE_CONDUCTION => '1234567'
    ];

    // User-friendly error messages
    public static $errors = [
        self::TYPE_PRIVATE => 'Private vehicle plates must be 3 letters and 3-4 numbers (e.g., ABC1234)',
        self::TYPE_MOTORCYCLE => 'Motorcycle plates must be 2 letters and 4-5 numbers (e.g., MC12345)',
        self::TYPE_GOVERNMENT => 'Government vehicle plates must be 2-3 letters and 1-4 numbers',
        self::TYPE_FOR_HIRE => 'For-hire vehicle plates must be 3 letters and 3-4 numbers',
        self::TYPE_ELECTRIC => 'Electric vehicle plates must start with E followed by 3 letters and 3-4 numbers (e.g., EABC1234)',
        self::TYPE_CONDUCTION => 'Conduction stickers must be 7-8 digits only'
    ];

    /**
     * Returns example format for a vehicle type
     */
    public static function getExample(string $type): string
    {
        return self::$examples[$type] ?? '';
    }

    /**
     * Validate a plate number against a vehicle type.
     * Returns [bool $valid, string $message]
     */
    public static function validate(string $type, string $plate): array
    {
        $plate = trim($plate);
        if ($plate === '') {
            return [false, 'Plate number is required.'];
        }
        if (!isset(self::$patterns[$type])) {
            return [false, 'Unknown vehicle type selected.'];
        }
        $pattern = self::$patterns[$type];
        if (preg_match($pattern, $plate)) {
            return [true, ''];
        }
        return [false, self::$errors[$type] ?? 'Invalid plate format.'];
    }

    /**
     * Normalize plate for storage: uppercase, remove spaces/hyphens
     */
    public static function normalize(string $plate): string
    {
        $p = strtoupper($plate);
        $p = preg_replace('/[\s\-]+/', '', $p);
        return $p;
    }

    /**
     * Normalize phone into +63XXXXXXXXXX format when possible
     */
    public static function normalizePhone(string $phone): string
    {
        $p = preg_replace('/[^0-9+]/', '', $phone);
        // If starts with 0 and 10 digits (09XXXXXXXXX), convert to +63
        if (preg_match('/^0(9[0-9]{9})$/', $p, $m)) {
            return '+63' . $m[1];
        }
        // If starts with 63 and 11 digits total, prefix +
        if (preg_match('/^63(9[0-9]{9})$/', $p, $m)) {
            return '+63' . $m[1];
        }
        // If starts with +63 already and 12 chars (+63XXXXXXXXXX)
        if (preg_match('/^\+63(9[0-9]{9})$/', $p)) {
            return $p;
        }
        // Fallback: return digits-only cleaned
        return $p;
    }
}

?>
