<?php

namespace Src\Models;

class ActionClosure extends Model
{
    protected string $table = 'action_closures';

    protected array $fillable = [
        'action_id',
        'requested_by',
        'closure_description',
        'evidence_files',
        'status',
        'reviewed_by',
        'review_notes',
        'reviewed_at',
        'requires_upper_approval',
        'upper_approved_by',
        'upper_review_notes',
        'upper_reviewed_at',
    ];

    public function getByAction(int $actionId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE action_id = ? ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$actionId]);
        return $stmt->fetchAll();
    }

    public function getPendingClosures(?int $reviewerId = null): array
    {
        $sql = "SELECT ac.*, a.title as action_title, a.company_id 
                FROM {$this->table} ac
                JOIN actions a ON ac.action_id = a.id
                WHERE ac.status = 'pending'";
        
        $params = [];
        
        if ($reviewerId) {
            $sql .= " AND (a.created_by = ? OR a.assigned_to_user_id = ?)";
            $params = [$reviewerId, $reviewerId];
        }
        
        $sql .= " ORDER BY ac.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getLatestByAction(int $actionId): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE action_id = ? ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$actionId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}
