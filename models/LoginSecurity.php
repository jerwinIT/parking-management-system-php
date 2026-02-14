<?php
/**
 * LoginSecurity helper
 * Tracks failed attempts and locks account after threshold.
 */
define('LOGINSEC_INCLUDED', true);

class LoginSecurity {
    // lock after this many failed attempts
    const MAX_ATTEMPTS = 5;
    // lock duration in seconds (1 minute)
    const LOCK_SECONDS = 60;

    private static function ensureTableExists() {
        $pdo = getDB();
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            identifier VARCHAR(255) NOT NULL UNIQUE,
            attempts_count INT NOT NULL DEFAULT 0,
            last_failed_at DATETIME DEFAULT NULL,
            locked_until DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }

    public static function getStatus(string $identifier): array {
        $identifier = strtolower(trim($identifier));
        if ($identifier === '') return ['attempts' => 0, 'locked_until' => null];
        self::ensureTableExists();
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT attempts_count, locked_until FROM login_attempts WHERE identifier = ? LIMIT 1');
        $stmt->execute([$identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return ['attempts' => 0, 'locked_until' => null];
        return ['attempts' => (int)$row['attempts_count'], 'locked_until' => $row['locked_until']];
    }

    public static function isLocked(string $identifier): array {
        $s = self::getStatus($identifier);
        if ($s['locked_until']) {
            $now = new DateTimeImmutable('now');
            $until = new DateTimeImmutable($s['locked_until']);
            if ($until > $now) {
                $secs = $until->getTimestamp() - $now->getTimestamp();
                return ['locked' => true, 'remaining' => $secs];
            }
        }
        return ['locked' => false, 'remaining' => 0];
    }

    public static function recordFailed(string $identifier) {
        $identifier = strtolower(trim($identifier));
        if ($identifier === '') return;
        self::ensureTableExists();
        $pdo = getDB();
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare('SELECT attempts_count FROM login_attempts WHERE identifier = ? LIMIT 1');
        $stmt->execute([$identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $count = (int)$row['attempts_count'] + 1;
            $lockedUntil = null;
            if ($count >= self::MAX_ATTEMPTS) {
                $lockedUntil = date('Y-m-d H:i:s', time() + self::LOCK_SECONDS);
            }
            $stmt = $pdo->prepare('UPDATE login_attempts SET attempts_count = ?, last_failed_at = ?, locked_until = ? WHERE identifier = ?');
            $stmt->execute([$count, $now, $lockedUntil, $identifier]);
        } else {
            $count = 1;
            $lockedUntil = ($count >= self::MAX_ATTEMPTS) ? date('Y-m-d H:i:s', time() + self::LOCK_SECONDS) : null;
            $stmt = $pdo->prepare('INSERT INTO login_attempts (identifier, attempts_count, last_failed_at, locked_until) VALUES (?, ?, ?, ?)');
            $stmt->execute([$identifier, $count, $now, $lockedUntil]);
        }
    }

    public static function reset(string $identifier) {
        $identifier = strtolower(trim($identifier));
        if ($identifier === '') return;
        self::ensureTableExists();
        $pdo = getDB();
        $stmt = $pdo->prepare('UPDATE login_attempts SET attempts_count = 0, last_failed_at = NULL, locked_until = NULL WHERE identifier = ?');
        $stmt->execute([$identifier]);
    }

    public static function formatRemaining(int $seconds): string {
        $m = floor($seconds / 60);
        $s = $seconds % 60;
        return sprintf('%d minutes %02d seconds', $m, $s);
    }
}

?>