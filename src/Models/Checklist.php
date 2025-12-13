<?php

namespace Src\Models;

class Checklist extends Model
{
    protected string $table = 'checklists';

    protected array $fillable = [
        'company_id',
        'name',
        'description',
        'status',
        'general_responsible_id',
        'created_by',
    ];

    public function withQuestions(int $id): ?array
    {
        $checklist = $this->find($id);

        if (!$checklist) {
            return null;
        }

        $sql = "SELECT * FROM checklist_questions WHERE checklist_id = ? ORDER BY order_num ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);

        $checklist['questions'] = $stmt->fetchAll();
        $checklist['question_count'] = count($checklist['questions']);

        return $checklist;
    }

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
}
