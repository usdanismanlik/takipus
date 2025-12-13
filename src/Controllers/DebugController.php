<?php

namespace Src\Controllers;

use Src\Helpers\Response;

class DebugController
{
    public static function envInfo()
    {
        $envData = [
            'getenv' => [
                'DB_HOST' => getenv('DB_HOST') ?: 'NOT SET',
                'DB_PORT' => getenv('DB_PORT') ?: 'NOT SET',
                'DB_NAME' => getenv('DB_NAME') ?: 'NOT SET',
                'DB_USER' => getenv('DB_USER') ?: 'NOT SET',
                'DB_PASSWORD' => getenv('DB_PASSWORD') ? 'SET (' . strlen(getenv('DB_PASSWORD')) . ' chars) - First 3: ' . substr(getenv('DB_PASSWORD'), 0, 3) : 'NOT SET',
                'JWT_SECRET' => getenv('JWT_SECRET') ? 'SET (' . strlen(getenv('JWT_SECRET')) . ' chars)' : 'NOT SET',
            ],
            '$_ENV' => [
                'DB_HOST' => $_ENV['DB_HOST'] ?? 'NOT SET',
                'DB_PORT' => $_ENV['DB_PORT'] ?? 'NOT SET',
                'DB_NAME' => $_ENV['DB_NAME'] ?? 'NOT SET',
                'DB_USER' => $_ENV['DB_USER'] ?? 'NOT SET',
                'DB_PASSWORD' => isset($_ENV['DB_PASSWORD']) ? 'SET (' . strlen($_ENV['DB_PASSWORD']) . ' chars) - First 3: ' . substr($_ENV['DB_PASSWORD'], 0, 3) : 'NOT SET',
                'JWT_SECRET' => isset($_ENV['JWT_SECRET']) ? 'SET (' . strlen($_ENV['JWT_SECRET']) . ' chars)' : 'NOT SET',
            ],
            'final_values' => [
                'host' => getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'mysql'),
                'port' => getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306'),
                'database' => getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'hse_db'),
                'username' => getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'hse_user'),
                'password_set' => !empty(getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? '')),
                'password_length' => strlen(getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? '')),
            ],
            'php_info' => [
                'version' => PHP_VERSION,
                'sapi' => PHP_SAPI,
                'os' => PHP_OS,
            ]
        ];

        Response::success($envData, 'Environment debug info');
    }

    public static function testDbConnection()
    {
        try {
            $host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'mysql');
            $port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306');
            $dbname = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'hse_db');
            $username = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'hse_user');
            $password = getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? '');

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            
            $pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);

            $version = $pdo->query('SELECT VERSION()')->fetchColumn();

            Response::success([
                'connection' => 'SUCCESS',
                'mysql_version' => $version,
                'host' => $host,
                'port' => $port,
                'database' => $dbname,
                'username' => $username,
            ], 'Database connection successful');

        } catch (\PDOException $e) {
            Response::error('Database connection failed: ' . $e->getMessage(), 500, [
                'connection' => 'FAILED',
                'error' => $e->getMessage(),
                'host' => $host ?? 'unknown',
                'port' => $port ?? 'unknown',
                'database' => $dbname ?? 'unknown',
                'username' => $username ?? 'unknown',
            ]);
        }
    }
}
