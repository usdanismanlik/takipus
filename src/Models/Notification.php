<?php

namespace Src\Models;

class Notification extends Model
{
    protected string $table = 'notifications';

    protected array $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'is_read',
        'read_at',
    ];

    public function markAsRead(int $id): bool
    {
        return $this->update($id, [
            'is_read' => true,
            'read_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markAllAsRead(int $userId): bool
    {
        $sql = "UPDATE {$this->table} SET is_read = 1, read_at = ? WHERE user_id = ? AND is_read = 0";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([date('Y-m-d H:i:s'), $userId]);
    }
}
