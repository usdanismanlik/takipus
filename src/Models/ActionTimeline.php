<?php

namespace Src\Models;

class ActionTimeline extends Model
{
    protected string $table = 'action_timeline';

    protected array $fillable = [
        'action_id',
        'event_type',
        'user_id',
        'title',
        'description',
        'metadata',
    ];

    /**
     * Aksiyonun timeline'ını getir (en yeni üstte)
     */
    public function getByAction(int $actionId): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE action_id = ? 
                ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$actionId]);
        return $stmt->fetchAll();
    }

    /**
     * Timeline kaydı oluştur
     */
    public function addEvent(
        int $actionId,
        string $eventType,
        int $userId,
        string $title,
        ?string $description = null,
        ?array $metadata = null
    ): int {
        return $this->create([
            'action_id' => $actionId,
            'event_type' => $eventType,
            'user_id' => $userId,
            'title' => $title,
            'description' => $description,
            'metadata' => $metadata ? json_encode($metadata) : null,
        ]);
    }
}
