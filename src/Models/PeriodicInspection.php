<?php

namespace Src\Models;

class PeriodicInspection extends Model
{
    protected string $table = 'periodic_inspections';

    protected array $fillable = [
        'company_id',
        'equipment_name',
        'equipment_code',
        'inspection_type',
        'inspection_frequency',
        'last_inspection_date',
        'next_inspection_date',
        'responsible_user_id',
        'location',
        'status',
        'notes',
        'photos',
        'created_by',
        'qr_code_url',
    ];

    public function getByCompany(string $companyId, ?string $status = null, ?string $equipmentCode = null): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE company_id = ?";
        $params = [$companyId];

        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        if ($equipmentCode) {
            $sql .= " AND equipment_code = ?";
            $params[] = $equipmentCode;
        }

        $sql .= " ORDER BY next_inspection_date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getUpcoming(int $daysAhead = 7): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE status = 'active'
                AND next_inspection_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                AND next_inspection_date >= CURDATE()
                ORDER BY next_inspection_date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$daysAhead]);
        return $stmt->fetchAll();
    }

    public function getOverdue(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE status = 'active'
                AND next_inspection_date < CURDATE()
                ORDER BY next_inspection_date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
