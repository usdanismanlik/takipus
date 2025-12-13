<?php

namespace Src\Models;

class FieldTour extends Model
{
    protected string $table = 'field_tours';

    protected array $fillable = [
        'company_id',
        'checklist_id',
        'inspector_user_id',
        'status',
        'started_at',
        'completed_at',
        'location',
        'notes',
    ];

    public function getWithResponses(int $id): ?array
    {
        $tour = $this->find($id);
        if (!$tour) {
            return null;
        }

        $sql = "SELECT r.*, q.question_text, q.question_type 
                FROM field_tour_responses r
                JOIN checklist_questions q ON r.question_id = q.id
                WHERE r.field_tour_id = ?
                ORDER BY q.order_num ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $tour['responses'] = $stmt->fetchAll();

        return $tour;
    }

    public function getByCompany(string $companyId, ?string $status = null): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE company_id = ?";
        $params = [$companyId];

        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY started_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
