<?php

namespace Src\Models;

class Checklist extends Model
{
    protected string $table = 'checklists';

    protected array $fillable = [
        'name',
        'description',
        'department_id',
        'status',
        'created_by',
    ];

    public function withQuestions(int $id): ?array
    {
        $checklist = $this->find($id);

        if (!$checklist) {
            return null;
        }

        $sql = "SELECT * FROM checklist_questions WHERE checklist_id = ? ORDER BY order_num";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);

        $checklist['questions'] = $stmt->fetchAll();
        $checklist['question_count'] = count($checklist['questions']);

        return $checklist;
    }
}
