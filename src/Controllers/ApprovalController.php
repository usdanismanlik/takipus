<?php

namespace Src\Controllers;

use Src\Models\ActionClosure;
use Src\Models\Action;
use Src\Middleware\AuthMiddleware;
use Src\Helpers\Response;

class ApprovalController
{
    private ActionClosure $closureModel;
    private Action $actionModel;

    public function __construct()
    {
        $this->closureModel = new ActionClosure();
        $this->actionModel = new Action();
    }

    /**
     * Onay bekleyen kapatma talepleri
     */
    public function closureRequests(): void
    {
        $status = $_GET['status'] ?? 'pending';
        $departmentId = $_GET['department_id'] ?? null;

        $sql = "SELECT 
                    ac.*,
                    a.id as action_id,
                    a.code as action_code,
                    a.title as action_title,
                    a.department_id
                FROM action_closures ac
                JOIN actions a ON ac.action_id = a.id
                WHERE ac.status = ?";

        $params = [$status];

        if ($departmentId) {
            $sql .= " AND a.department_id = ?";
            $params[] = $departmentId;
        }

        $sql .= " ORDER BY ac.created_at DESC";

        $stmt = $this->closureModel->db->prepare($sql);
        $stmt->execute($params);
        $closures = $stmt->fetchAll();

        $result = [];
        foreach ($closures as $closure) {
            $result[] = [
                'id' => (int) $closure['id'],
                'action' => [
                    'id' => (int) $closure['action_id'],
                    'code' => $closure['action_code'],
                    'title' => $closure['action_title'],
                ],
                'submitted_by' => [
                    'id' => (int) $closure['submitted_by_user_id'],
                    'name' => 'User ' . $closure['submitted_by_user_id'], // TODO: Get from user service
                ],
                'completion_notes' => $closure['completion_notes'],
                'completion_date' => $closure['completion_date'],
                'submitted_at' => $closure['created_at'],
                'status' => $closure['status'],
            ];
        }

        Response::success($result);
    }

    /**
     * Kapatma talebini onayla
     */
    public function approve(int $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = AuthMiddleware::getUserId();

        $closure = $this->closureModel->find($id);

        if (!$closure) {
            Response::error('Closure request not found', 404);
            return;
        }

        if ($closure['status'] !== 'pending') {
            Response::error('Closure request is not pending', 400);
            return;
        }

        $this->closureModel->approve($id, $userId, $data['notes'] ?? null);

        $updatedClosure = $this->closureModel->find($id);
        $action = $this->actionModel->find($closure['action_id']);

        Response::success([
            'id' => (int) $updatedClosure['id'],
            'status' => $updatedClosure['status'],
            'action_status' => $action['status'],
            'approved_by' => [
                'id' => $userId,
                'name' => 'Current User', // TODO: Get from user service
            ],
            'approved_at' => $updatedClosure['approved_at'],
        ], 'Closure request approved, action closed');
    }

    /**
     * Kapatma talebini reddet
     */
    public function reject(int $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['reason'])) {
            Response::error('Rejection reason is required', 422);
            return;
        }

        $userId = AuthMiddleware::getUserId();

        $closure = $this->closureModel->find($id);

        if (!$closure) {
            Response::error('Closure request not found', 404);
            return;
        }

        if ($closure['status'] !== 'pending') {
            Response::error('Closure request is not pending', 400);
            return;
        }

        $this->closureModel->reject($id, $userId, $data['reason']);

        $updatedClosure = $this->closureModel->find($id);

        Response::success([
            'id' => (int) $updatedClosure['id'],
            'status' => $updatedClosure['status'],
            'rejected_by' => [
                'id' => $userId,
                'name' => 'Current User', // TODO: Get from user service
            ],
            'rejected_at' => $updatedClosure['approved_at'],
            'rejection_reason' => $updatedClosure['rejection_reason'],
        ], 'Closure request rejected');
    }
}
