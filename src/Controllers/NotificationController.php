<?php

namespace Src\Controllers;

use Src\Models\Notification;
use Src\Middleware\AuthMiddleware;
use Src\Helpers\Response;

class NotificationController
{
    private Notification $notificationModel;

    public function __construct()
    {
        $this->notificationModel = new Notification();
    }

    public function index(): void
    {
        $userId = AuthMiddleware::getUserId();
        $page = (int) ($_GET['page'] ?? 1);
        $perPage = (int) ($_GET['per_page'] ?? 20);
        $offset = ($page - 1) * $perPage;

        $notifications = $this->notificationModel->all(['user_id' => $userId], $perPage, $offset);
        $total = $this->notificationModel->count(['user_id' => $userId]);
        $unreadCount = $this->notificationModel->count(['user_id' => $userId, 'is_read' => 0]);

        Response::success([
            'items' => $notifications,
            'unread_count' => $unreadCount,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
            ],
        ]);
    }

    public function markAsRead(int $id): void
    {
        $this->notificationModel->markAsRead($id);
        Response::success(null, 'Notification marked as read');
    }

    public function markAllAsRead(): void
    {
        $userId = AuthMiddleware::getUserId();
        $this->notificationModel->markAllAsRead($userId);
        Response::success(null, 'All notifications marked as read');
    }
}
