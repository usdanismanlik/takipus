<?php

use Src\Config\Database;

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

try {
    $db = Database::getConnection();

    // Check if column exists
    $checkStmt = $db->prepare("
        SELECT COUNT(*) 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = :dbname 
        AND TABLE_NAME = 'periodic_inspections' 
        AND COLUMN_NAME = 'qr_code_url'
    ");

    $dbname = $_ENV['DB_NAME'];
    $checkStmt->execute(['dbname' => $dbname]);

    if ($checkStmt->fetchColumn() == 0) {
        $sql = "ALTER TABLE periodic_inspections ADD COLUMN qr_code_url VARCHAR(255) NULL AFTER notes";
        $db->exec($sql);
        echo "Successfully added 'qr_code_url' column to 'periodic_inspections' table.\n";
    } else {
        echo "Column 'qr_code_url' already exists.\n";
    }

} catch (PDOException $e) {
    echo "Migration Failed: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
