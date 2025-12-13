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
                // Try getenv first (for CapRover), then $_ENV (for local .env)
                $host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'mysql');
                $port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306');
                $dbname = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'hse_db');
                $username = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'hse_user');
                $password = getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? '');

                // Debug log - FORCE to stderr
                $debug = "\n=== DB Connection Debug ===\n";
                $debug .= "getenv('DB_HOST'): " . (getenv('DB_HOST') ?: 'NOT SET') . "\n";
                $debug .= "getenv('DB_USER'): " . (getenv('DB_USER') ?: 'NOT SET') . "\n";
                $debug .= "getenv('DB_PASSWORD'): " . (getenv('DB_PASSWORD') ? 'SET (' . strlen(getenv('DB_PASSWORD')) . ' chars)' : 'NOT SET') . "\n";
                $debug .= "\$_ENV['DB_HOST']: " . ($_ENV['DB_HOST'] ?? 'NOT SET') . "\n";
                $debug .= "\$_ENV['DB_USER']: " . ($_ENV['DB_USER'] ?? 'NOT SET') . "\n";
                $debug .= "Final Host: {$host}\n";
                $debug .= "Final Port: {$port}\n";
                $debug .= "Final Database: {$dbname}\n";
                $debug .= "Final Username: {$username}\n";
                $debug .= "Final Password: " . (!empty($password) ? 'YES (' . strlen($password) . ' chars) - First 3: ' . substr($password, 0, 3) : 'NO') . "\n";
                $debug .= "========================\n\n";
                
                file_put_contents('php://stderr', $debug);
                error_log($debug);

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
