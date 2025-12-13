<?php

namespace Src\Models;

class Action extends Model
{
    protected string $table = 'actions';

    protected array $fillable = [
        'code',
        'title',
        'description',
        'location',
        'department_id',
        'assigned_to_user_id',
        'assigned_by_user_id',
        'source',
        'risk_level',
        'risk_score',
        'priority',
        'status',
        'due_date',
    ];

    public function generateCode(): string
    {
        $year = date('Y');
        $count = $this->count() + 1;
        return "HSE-{$year}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    public function withComments(int $id): ?array
    {
        $action = $this->find($id);

        if (!$action) {
            return null;
        }

        $sql = "SELECT * FROM action_comments WHERE action_id = ? ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);

        $action['comments'] = $stmt->fetchAll();

        return $action;
    }
}
