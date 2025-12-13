<?php

namespace Src\Helpers;

use Src\Models\AuditLog;

class AuditLogger
{
    private static ?AuditLog $auditLogModel = null;

    private static function getModel(): AuditLog
    {
        if (self::$auditLogModel === null) {
            self::$auditLogModel = new AuditLog();
        }
        return self::$auditLogModel;
    }

    /**
     * Log oluştur
     */
    public static function log(
        string $action,
        string $endpoint,
        ?string $resourceType = null,
        ?int $resourceId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null
    ): void {
        try {
            $model = self::getModel();
            
            $model->create([
                'user_id' => $userId,
                'action' => $action,
                'endpoint' => $endpoint,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'old_values' => $oldValues ? json_encode($oldValues) : null,
                'new_values' => $newValues ? json_encode($newValues) : null,
                'ip_address' => self::getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (\Exception $e) {
            // Log hatası uygulamayı durdurmamalı
            error_log("Audit log error: " . $e->getMessage());
        }
    }

    /**
     * POST işlemi logla
     */
    public static function logCreate(
        string $endpoint,
        string $resourceType,
        int $resourceId,
        array $newValues,
        ?int $userId = null
    ): void {
        self::log('POST', $endpoint, $resourceType, $resourceId, null, $newValues, $userId);
    }

    /**
     * PUT işlemi logla
     */
    public static function logUpdate(
        string $endpoint,
        string $resourceType,
        int $resourceId,
        array $oldValues,
        array $newValues,
        ?int $userId = null
    ): void {
        self::log('PUT', $endpoint, $resourceType, $resourceId, $oldValues, $newValues, $userId);
    }

    /**
     * DELETE işlemi logla
     */
    public static function logDelete(
        string $endpoint,
        string $resourceType,
        int $resourceId,
        array $oldValues,
        ?int $userId = null
    ): void {
        self::log('DELETE', $endpoint, $resourceType, $resourceId, $oldValues, null, $userId);
    }

    /**
     * Client IP adresini al
     */
    private static function getClientIp(): ?string
    {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    /**
     * Kayıt değişikliklerini karşılaştır
     */
    public static function getChanges(array $oldValues, array $newValues): array
    {
        $changes = [];
        
        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;
            
            if ($oldValue != $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }
        
        return $changes;
    }
}
