<?php

namespace Src\Controllers;

use Src\Models\Notification;
use Src\Helpers\Response;

class NotificationController
{
    private Notification $notificationModel;

    public function __construct()
    {
        $this->notificationModel = new Notification();
    }

    /**
     * GET /api/v1/notifications
     * Tüm bildirimleri getir (debug için)
     */
    public function index(): void
    {
        $companyId = $_GET['company_id'] ?? null;

        if (!$companyId) {
            Response::error('company_id parametresi gerekli', 422);
            return;
        }

        try {
            // Tüm bildirimleri getir - company_id'ye göre filtreleme yapılabilir
            // Şimdilik tüm bildirimleri döndür
            $sql = "SELECT * FROM notifications ORDER BY created_at DESC LIMIT 1000";
            $stmt = $this->notificationModel->getDb()->prepare($sql);
            $stmt->execute();
            $notifications = $stmt->fetchAll();

            Response::success($notifications);
        } catch (\Exception $e) {
            error_log("Notification index error: " . $e->getMessage());
            Response::error('Bildirimler getirilemedi: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/notifications/user/:userId
     * Belirli bir kullanıcının bildirimlerini getir
     */
    public function getByUser(int $userId): void
    {
        $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';

        try {
            $notifications = $this->notificationModel->getByUser($userId, $unreadOnly);
            Response::success($notifications);
        } catch (\Exception $e) {
            error_log("Get user notifications error: " . $e->getMessage());
            Response::error('Kullanıcı bildirimleri getirilemedi: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/v1/notifications/:id/read
     * Bildirimi okundu olarak işaretle
     */
    public function markAsRead(int $id): void
    {
        try {
            $result = $this->notificationModel->markAsRead($id);
            
            if ($result) {
                Response::success(['message' => 'Bildirim okundu olarak işaretlendi']);
            } else {
                Response::error('Bildirim güncellenemedi', 500);
            }
        } catch (\Exception $e) {
            error_log("Mark notification as read error: " . $e->getMessage());
            Response::error('Bildirim güncellenemedi: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/v1/notifications/user/:userId/read-all
     * Kullanıcının tüm bildirimlerini okundu olarak işaretle
     */
    public function markAllAsRead(int $userId): void
    {
        try {
            $result = $this->notificationModel->markAllAsReadForUser($userId);
            
            if ($result) {
                Response::success(['message' => 'Tüm bildirimler okundu olarak işaretlendi']);
            } else {
                Response::error('Bildirimler güncellenemedi', 500);
            }
        } catch (\Exception $e) {
            error_log("Mark all notifications as read error: " . $e->getMessage());
            Response::error('Bildirimler güncellenemedi: ' . $e->getMessage(), 500);
        }
    }
}
