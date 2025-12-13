<?php

namespace Src\Models;

class ActionTimeline extends Model
{
    protected string $table = 'action_timeline';

    protected array $fillable = [
        'action_id',
        'user_id',
        'action_type',
        'old_value',
        'new_value',
        'notes',
    ];

    /**
     * Get timeline for an action
     * Note: User info should be fetched separately from external user API
     */
    public function getByAction(int $actionId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE action_id = ? ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$actionId]);
        return $stmt->fetchAll();
    }

    /**
     * Add timeline event
     */
    public function addEvent(int $actionId, string $actionType, ?string $oldValue = null, ?string $newValue = null, ?string $notes = null): int
    {
        $userId = 1; // TODO: Get from auth

        return $this->create([
            'action_id' => $actionId,
            'user_id' => $userId,
            'action_type' => $actionType,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'notes' => $notes,
        ]);
    }
}
