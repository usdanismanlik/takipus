<?php

namespace Src\Models;

class ChecklistQuestion extends Model
{
    protected string $table = 'checklist_questions';

    protected array $fillable = [
        'checklist_id',
        'order_num',
        'question_text',
        'question_type',
        'is_required',
        'photo_required',
        'help_text',
        'min_score',
        'max_score',
        'responsible_user_ids',
    ];

    public function getByChecklist(int $checklistId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE checklist_id = ? ORDER BY order_num ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$checklistId]);
        return $stmt->fetchAll();
    }

    public function deleteByChecklist(int $checklistId): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE checklist_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$checklistId]);
    }
}
