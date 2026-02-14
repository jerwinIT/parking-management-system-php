<!-- <?php
/**
 * Dev helper: mark all existing accounts as email-verified.
 * USAGE (browser): /dev/mark_all_verified.php?token=REPLACE_ME
 * USAGE (cli): php dev/mark_all_verified.php REPLACE_ME
 * After successful run delete this file.
 */
// define('PARKING_ACCESS', true);
// require_once dirname(__DIR__) . '/config/init.php';

// $secret = 'fix-login-2026'; // change if you copy this file to a public environment
// $provided = '';
// if (php_sapi_name() === 'cli') {
//     $provided = $argv[1] ?? '';
// } else {
//     $provided = $_GET['token'] ?? '';
// }

// if ($provided !== $secret) {
//     header('Content-Type: text/plain; charset=utf-8');
//     echo "Missing or invalid token. Provide ?token=$secret\n";
//     exit;
// }

// try {
//     $pdo = getDB();
//     $sql = "UPDATE users SET email_verified = 1, verification_token = NULL, verification_token_expires = NULL WHERE email_verified = 0 OR email_verified IS NULL";
//     $affected = $pdo->exec($sql);
//     header('Content-Type: text/plain; charset=utf-8');
//     echo "Success: $affected row(s) updated.\n";
//     echo "Please delete this file after use.\n";
// } catch (Exception $e) {
//     header('Content-Type: text/plain; charset=utf-8');
//     echo "Error: " . $e->getMessage() . "\n";
// }

// ?> -->
