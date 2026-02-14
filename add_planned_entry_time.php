<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=parking_management_db', 'root', '');
try {
    $pdo->exec('ALTER TABLE bookings ADD COLUMN planned_entry_time DATETIME NULL AFTER entry_time');
    echo 'Column added successfully';
} catch (Exception $e) {
    if (strpos($e->getMessage(), '1060') !== false) {
        echo 'Column already exists';
    } else {
        echo 'Error: ' . $e->getMessage();
    }
}
?>
