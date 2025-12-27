<?php

namespace Src\Models;

use Src\Services\CoreService;

class Notification extends Model
{
    protected string $table = 'notifications';

    protected array $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'related_type',
        'related_id',
        'is_read',
        'read_at',
    ];

    /**
     * Override create method to automatically send push notification
     */
    public function create(array $data): int
    {
        // Önce DB'ye kaydet
        $notificationId = parent::create($data);

        // Push notification gönder
        if (isset($data['user_id']) && isset($data['title']) && isset($data['message'])) {
            try {
                // Temel push data
                $pushData = [
                    'notification_id' => $notificationId,
                    'type' => $data['type'] ?? 'general',
                    'related_type' => $data['related_type'] ?? null,
                    'related_id' => $data['related_id'] ?? null,
                ];

                // Eğer action bildirimi ise action_id ekle
                if ($data['related_type'] === 'action' && isset($data['related_id'])) {
                    $pushData['action_id'] = $data['related_id'];
                }

                // Eğer ek data varsa (örn: closure_id) merge et
                if (isset($data['data'])) {
                    $extraData = is_string($data['data']) ? json_decode($data['data'], true) : $data['data'];
                    if (is_array($extraData)) {
                        $pushData = array_merge($pushData, $extraData);
                    }
                }

                CoreService::sendPushNotification(
                    (int) $data['user_id'],
                    $data['title'],
                    $data['message'],
                    $pushData
                );
            } catch (\Exception $e) {
                // Push hatası DB kaydını engellemez, sadece logla
                error_log("Push notification failed for user {$data['user_id']}: " . $e->getMessage());
            }
        }

        return $notificationId;
    }

    public function getByUser(int $userId, bool $unreadOnly = false): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = ?";
        $params = [$userId];

        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function markAsRead(int $id): bool
    {
        $sql = "UPDATE {$this->table} SET is_read = 1, read_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    public function markAllAsReadForUser(int $userId): bool
    {
        $sql = "UPDATE {$this->table} SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$userId]);
    }
}
