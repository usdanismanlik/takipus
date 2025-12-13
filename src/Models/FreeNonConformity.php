<?php

namespace Src\Models;

class FreeNonConformity extends Model
{
    protected string $table = 'free_nonconformities';

    protected array $fillable = [
        'company_id',
        'title',
        'description',
        'location',
        'assigned_to_user_ids',
        'priority',
        'risk_score',
        'photos',
        'status',
        'due_date',
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
        $sql = "SELECT * FROM {$this->table} WHERE JSON_CONTAINS(assigned_to_user_ids, ?)";
        $params = [json_encode($userId)];

        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
