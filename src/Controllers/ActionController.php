<?php

namespace Src\Controllers;

use Src\Models\Action;
use Src\Models\ActionTimeline;
use Src\Middleware\AuthMiddleware;
use Src\Helpers\Response;

class ActionController
{
    private Action $actionModel;

    public function __construct()
    {
        $this->actionModel = new Action();
    }

    public function index(): void
    {
        $page = (int) ($_GET['page'] ?? 1);
        $perPage = (int) ($_GET['per_page'] ?? 20);
        $offset = ($page - 1) * $perPage;

        $conditions = [];
        if (isset($_GET['status'])) {
            $conditions['status'] = $_GET['status'];
        }

        $actions = $this->actionModel->all($conditions, $perPage, $offset);
        $total = $this->actionModel->count($conditions);

        Response::paginated($actions, $total, $page, $perPage);
    }

    public function show(int $id): void
    {
        $action = $this->actionModel->withComments($id);

        if (!$action) {
            Response::error('Action not found', 404);
            return;
        }

        Response::success($action);
    }

    public function store(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['title']) || !isset($data['description'])) {
            Response::error('Title and description are required', 422);
            return;
        }

        $userId = AuthMiddleware::getUserId();

        $data['code'] = $this->actionModel->generateCode();
        $data['assigned_by_user_id'] = $userId;
        $data['source'] = $data['source'] ?? 'manual';
        $data['status'] = 'open';

        $id = $this->actionModel->create($data);
        $action = $this->actionModel->find($id);

        Response::success($action, 'Action created', 201);
    }

    /**
     * Add comment to action
     */
    public function addComment(int $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['comment'])) {
            Response::error('Comment is required', 422);
            return;
        }

        $action = $this->actionModel->find($id);
        if (!$action) {
            Response::error('Action not found', 404);
            return;
        }

        $sql = "INSERT INTO action_comments (action_id, comment, user_id) VALUES (?, ?, ?)";
        $stmt = $this->actionModel->db->prepare($sql);
        $stmt->execute([$id, $data['comment'], 1]); // TODO: Get user_id from auth

        $commentId = $this->actionModel->db->lastInsertId();

        // Add timeline event
        $timelineModel = new ActionTimeline();
        $timelineModel->addEvent($id, 'comment_added', null, null, 'Yorum eklendi: ' . $data['comment']);

        Response::success([
            'id' => $commentId,
            'action_id' => $id,
            'comment' => $data['comment']
        ], 'Comment added', 201);
    }

    /**
     * Get action timeline
     */
    public function timeline(int $id): void
    {
        $action = $this->actionModel->find($id);
        if (!$action) {
            Response::error('Action not found', 404);
            return;
        }

        $timelineModel = new ActionTimeline();
        $timeline = $timelineModel->getByAction($id);

        Response::success($timeline);
    }

    /**
     * Update action status
     */
    public function updateStatus(int $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['status'])) {
            Response::error('Status is required', 422);
            return;
        }

        $action = $this->actionModel->find($id);
        if (!$action) {
            Response::error('Action not found', 404);
            return;
        }

        $oldStatus = $action['status'];
        $newStatus = $data['status'];

        $this->actionModel->update($id, ['status' => $newStatus]);

        // Add timeline event
        $timelineModel = new ActionTimeline();
        $statusLabels = [
            'open' => 'Açık',
            'in_progress' => 'Devam Ediyor',
            'pending_approval' => 'Onay Bekliyor',
            'closed' => 'Kapalı'
        ];

        $timelineModel->addEvent(
            $id,
            'status_changed',
            $statusLabels[$oldStatus] ?? $oldStatus,
            $statusLabels[$newStatus] ?? $newStatus,
            'Durum değiştirildi'
        );

        Response::success([
            'id' => $id,
            'status' => $newStatus
        ], 'Status updated');
    }

    /**
     * Assign action to user
     */
    public function assign(int $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['user_id'])) {
            Response::error('User ID is required', 422);
            return;
        }

        $action = $this->actionModel->find($id);
        if (!$action) {
            Response::error('Action not found', 404);
            return;
        }

        $this->actionModel->update($id, ['assigned_to_user_id' => $data['user_id']]);

        // Add timeline event
        $timelineModel = new ActionTimeline();
        $timelineModel->addEvent(
            $id,
            'assigned',
            null,
            'User #' . $data['user_id'],
            'Aksiyon atandı'
        );

        Response::success([
            'id' => $id,
            'assigned_to_user_id' => $data['user_id']
        ], 'Action assigned');
    }
}
