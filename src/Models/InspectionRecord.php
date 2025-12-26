<?php

namespace Src\Models;

class InspectionRecord extends Model
{
    protected string $table = 'periodic_inspection_records';

    protected array $fillable = [
        'company_id',
        'inspection_id',
        'inspection_date',
        'inspector_user_id',
        'status',
        'findings',
        'photos',
        'next_inspection_date',
    ];

    /**
     * Ekipmanın kontrol geçmişini getir
     */
    public function getByInspection(int $inspectionId): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE inspection_id = ? 
                ORDER BY inspection_date DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$inspectionId]);
        return $stmt->fetchAll();
    }

    /**
     * Yeni kontrol kaydı oluştur
     */
    public function createRecord(array $data): int
    {
        // Kontrol kaydını oluştur
        $id = $this->create($data);

        // Ekipmanın next_inspection_date ve last_inspection_date'ini güncelle
        if (isset($data['next_inspection_date'])) {
            $sql = "UPDATE periodic_inspections 
                    SET next_inspection_date = ?,
                        last_inspection_date = ?
                    WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['next_inspection_date'],
                $data['inspection_date'],
                $data['inspection_id']
            ]);
        }

        return $id;
    }

    /**
     * Son X gün içinde kontrol yapılmış mı?
     */
    public function hasRecentInspection(int $inspectionId, int $days = 7): bool
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                WHERE inspection_id = ? 
                AND inspection_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$inspectionId, $days]);
        $result = $stmt->fetch();

        return $result['count'] > 0;
    }

    /**
     * Ekipman için son kontrol kaydını getir
     */
    public function getLatestByInspection(int $inspectionId): ?array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE inspection_id = ? 
                ORDER BY inspection_date DESC 
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$inspectionId]);
        $result = $stmt->fetch();

        return $result ?: null;
    }
}
