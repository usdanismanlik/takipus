<?php

namespace Src\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            try {
                $host = $_ENV['DB_HOST'] ?? 'mysql';
                $port = $_ENV['DB_PORT'] ?? '3306';
                $dbname = $_ENV['DB_NAME'] ?? 'hse_db';
                $username = $_ENV['DB_USER'] ?? 'hse_user';
                $password = $_ENV['DB_PASSWORD'] ?? '';

                // Debug log (production'da kaldırılacak)
                error_log("DB Connection Attempt - Host: {$host}, Port: {$port}, DB: {$dbname}, User: {$username}");

                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

                self::$connection = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                
                error_log("DB Connection SUCCESS");
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                error_log("Connection details - Host: {$host}, Port: {$port}, DB: {$dbname}, User: {$username}");
                die(json_encode([
                    'success' => false,
                    'error' => [
                        'message' => 'Database connection failed',
                        'details' => $e->getMessage()
                    ]
                ]));
            }
        }

        return self::$connection;
    }
}
