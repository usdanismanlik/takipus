<?php

namespace Src\Models;

class AuditLog extends Model
{
    protected string $table = 'audit_logs';

    protected array $fillable = [
        'user_id',
        'action',
        'endpoint',
        'resource_type',
        'resource_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    public function getByUser(int $userId, ?int $limit = 100): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    public function getByResource(string $resourceType, int $resourceId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE resource_type = ? AND resource_id = ? ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$resourceType, $resourceId]);
        return $stmt->fetchAll();
    }

    public function getByAction(string $action, ?int $limit = 100): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE action = ? ORDER BY created_at DESC LIMIT ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$action, $limit]);
        return $stmt->fetchAll();
    }
}
