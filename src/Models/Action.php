<?php

namespace Src\Models;

class Action extends Model
{
    protected string $table = 'actions';

    protected array $fillable = [
        'company_id',
        'field_tour_id',
        'response_id',
        'checklist_id',
        'periodic_inspection_id',
        'title',
        'description',
        'photos',
        'location',
        'assigned_to_user_id',
        'assigned_to_department_id',
        'upper_approver_id',
        'status',
        'priority',
        'risk_score',
        'risk_probability',
        'risk_severity',
        'risk_level',
        'source_type',
        'due_date',
        'due_date_reminder_days',
        'last_reminder_sent_at',
        'is_overdue',
        'overdue_notification_sent',
        'completed_at',
        'created_by',
    ];

    public function getByCompany(string $companyId, ?string $status = null): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE company_id = ?";
        $params = [$companyId];

        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getByAssignedUser(int $userId, ?string $status = null): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE assigned_to_user_id = ?";
        $params = [$userId];

        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getOverdueActions(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE due_date < CURDATE() 
                AND status NOT IN ('completed', 'cancelled')
                AND is_overdue = 0
                ORDER BY due_date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getActionsNeedingReminder(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE due_date IS NOT NULL 
                AND status NOT IN ('completed', 'cancelled')
                AND due_date_reminder_days IS NOT NULL
                ORDER BY due_date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function markAsOverdue(int $id): bool
    {
        $sql = "UPDATE {$this->table} SET is_overdue = 1 WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Belirli bir periyodik kontrol için açık aksiyon var mı?
     */
    public function hasOpenActionForInspection(int $inspectionId): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} 
                WHERE periodic_inspection_id = ? 
                AND status NOT IN ('completed', 'cancelled')";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$inspectionId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
