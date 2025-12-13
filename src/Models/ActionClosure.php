<?php

namespace Src\Models;

class ActionClosure extends Model
{
    protected string $table = 'action_closures';

    protected array $fillable = [
        'action_id',
        'submitted_by_user_id',
        'completion_notes',
        'corrective_actions',
        'completion_date',
        'status',
        'approved_by_user_id',
        'approved_at',
        'rejection_reason',
    ];

    public function approve(int $id, int $userId, ?string $notes = null): bool
    {
        $result = $this->update($id, [
            'status' => 'approved',
            'approved_by_user_id' => $userId,
            'approved_at' => date('Y-m-d H:i:s'),
        ]);

        if ($result) {
            // Action'ı kapat
            $closure = $this->find($id);
            if ($closure) {
                $sql = "UPDATE actions SET status = 'closed' WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$closure['action_id']]);
            }
        }

        return $result;
    }

    public function reject(int $id, int $userId, string $reason): bool
    {
        return $this->update($id, [
            'status' => 'rejected',
            'approved_by_user_id' => $userId,
            'approved_at' => date('Y-m-d H:i:s'),
            'rejection_reason' => $reason,
        ]);
    }
}
