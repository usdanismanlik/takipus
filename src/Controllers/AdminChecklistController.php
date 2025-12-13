<?php

namespace Src\Controllers;

use Src\Models\Checklist;
use Src\Middleware\AuthMiddleware;
use Src\Helpers\Response;

class AdminChecklistController
{
    private Checklist $checklistModel;

    public function __construct()
    {
        $this->checklistModel = new Checklist();
    }

    /**
     * Admin checklist listesi
     */
    public function index(): void
    {
        $status = $_GET['status'] ?? null;
        $departmentId = $_GET['department_id'] ?? null;
        $search = $_GET['search'] ?? null;

        $conditions = [];
        if ($status) {
            $conditions['status'] = $status;
        }
        if ($departmentId) {
            $conditions['department_id'] = $departmentId;
        }

        $checklists = $this->checklistModel->all($conditions);

        // Search filter
        if ($search) {
            $checklists = array_filter($checklists, function ($checklist) use ($search) {
                return stripos($checklist['name'], $search) !== false ||
                    stripos($checklist['description'], $search) !== false;
            });
        }

        // Add question count and usage stats
        foreach ($checklists as &$checklist) {
            $sql = "SELECT COUNT(*) as count FROM checklist_questions WHERE checklist_id = ?";
            $stmt = $this->checklistModel->db->prepare($sql);
            $stmt->execute([$checklist['id']]);
            $checklist['question_count'] = (int) $stmt->fetchColumn();

            // Usage count
            $sql = "SELECT COUNT(*) as count FROM field_tours WHERE checklist_id = ?";
            $stmt = $this->checklistModel->db->prepare($sql);
            $stmt->execute([$checklist['id']]);
            $checklist['usage_count'] = (int) $stmt->fetchColumn();

            // Last used
            $sql = "SELECT MAX(created_at) as last_used FROM field_tours WHERE checklist_id = ?";
            $stmt = $this->checklistModel->db->prepare($sql);
            $stmt->execute([$checklist['id']]);
            $checklist['last_used_at'] = $stmt->fetchColumn();
        }

        Response::success(array_values($checklists));
    }

    /**
     * Checklist oluştur
     */
    public function store(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['name'])) {
            Response::error('Name is required', 422);
            return;
        }

        $userId = AuthMiddleware::getUserId();

        // Checklist oluştur
        $checklistId = $this->checklistModel->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'created_by' => $userId,
        ]);

        // Questions ekle
        if (isset($data['questions']) && is_array($data['questions'])) {
            foreach ($data['questions'] as $question) {
                $sql = "INSERT INTO checklist_questions (checklist_id, order_num, text, type, is_required, photo_required, help_text, min_score, max_score) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $this->checklistModel->db->prepare($sql);
                $stmt->execute([
                    $checklistId,
                    $question['order'] ?? 1,
                    $question['text'],
                    $question['type'],
                    isset($question['is_required']) ? (int)$question['is_required'] : 1,
                    isset($question['photo_required']) ? (int)$question['photo_required'] : 0,
                    $question['help_text'] ?? null,
                    $question['min_score'] ?? null,
                    $question['max_score'] ?? null,
                ]);
            }
        }

        $checklist = $this->checklistModel->withQuestions($checklistId);

        Response::success($checklist, 'Checklist created', 201);
    }

    /**
     * Checklist güncelle
     */
    public function update(int $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        // Checklist güncelle
        $updateData = [];
        if (isset($data['name']))
            $updateData['name'] = $data['name'];
        if (isset($data['description']))
            $updateData['description'] = $data['description'];
        if (isset($data['status']))
            $updateData['status'] = $data['status'];
        if (isset($data['department_id']))
            $updateData['department_id'] = $data['department_id'];

        if (!empty($updateData)) {
            $this->checklistModel->update($id, $updateData);
        }

        // Questions güncelle
        if (isset($data['questions']) && is_array($data['questions'])) {
            // Mevcut soruları sil
            $sql = "DELETE FROM checklist_questions WHERE checklist_id = ?";
            $stmt = $this->checklistModel->db->prepare($sql);
            $stmt->execute([$id]);

            // Yeni soruları ekle
            foreach ($data['questions'] as $question) {
                $sql = "INSERT INTO checklist_questions (checklist_id, order_num, text, type, is_required, photo_required, help_text, min_score, max_score) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $this->checklistModel->db->prepare($sql);
                $stmt->execute([
                    $id,
                    $question['order'] ?? 1,
                    $question['text'],
                    $question['type'],
                    isset($question['is_required']) ? (int)$question['is_required'] : 1,
                    isset($question['photo_required']) ? (int)$question['photo_required'] : 0,
                    $question['help_text'] ?? null,
                    $question['min_score'] ?? null,
                    $question['max_score'] ?? null,
                ]);
            }
        }

        $checklist = $this->checklistModel->withQuestions($id);

        Response::success($checklist, 'Checklist updated');
    }

    /**
     * Checklist sil/arşivle
     */
    public function destroy(int $id): void
    {
        $this->checklistModel->update($id, ['status' => 'archived']);
        Response::success(null, 'Checklist archived');
    }

    /**
     * Checklist kopyala
     */
    public function duplicate(int $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $original = $this->checklistModel->withQuestions($id);

        if (!$original) {
            Response::error('Checklist not found', 404);
            return;
        }

        $userId = AuthMiddleware::getUserId();

        // Yeni checklist oluştur
        $newId = $this->checklistModel->create([
            'name' => $data['name'] ?? $original['name'] . ' - Kopya',
            'description' => $original['description'],
            'department_id' => $original['department_id'],
            'status' => 'draft',
            'created_by' => $userId,
        ]);

        // Questions kopyala
        foreach ($original['questions'] as $question) {
            $sql = "INSERT INTO checklist_questions (checklist_id, order_num, text, type, is_required, photo_required, help_text, min_score, max_score) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->checklistModel->db->prepare($sql);
            $stmt->execute([
                $newId,
                $question['order_num'],
                $question['text'],
                $question['type'],
                (int)$question['is_required'],
                (int)$question['photo_required'],
                $question['help_text'],
                $question['min_score'],
                $question['max_score'],
            ]);
        }

        $newChecklist = $this->checklistModel->withQuestions($newId);

        Response::success($newChecklist, 'Checklist duplicated', 201);
    }
}
