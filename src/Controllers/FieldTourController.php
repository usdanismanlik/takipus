<?php

namespace Src\Controllers;

use Src\Models\Checklist;
use Src\Models\FieldTour;
use Src\Middleware\AuthMiddleware;
use Src\Helpers\Response;
use Src\Config\Database;
use PDO;
use Exception;

class FieldTourController
{
    private Checklist $checklistModel;
    private FieldTour $fieldTourModel;

    public function __construct()
    {
        $this->checklistModel = new Checklist();
        $this->fieldTourModel = new FieldTour();
    }

    /**
     * Get all active checklists
     */
    public function getChecklists(): void
    {
        $checklists = $this->checklistModel->all(['status' => 'active']);

        // Add question count
        foreach ($checklists as &$checklist) {
            $sql = "SELECT COUNT(*) as count FROM checklist_questions WHERE checklist_id = ?";
            $stmt = $this->checklistModel->db->prepare($sql);
            $stmt->execute([$checklist['id']]);
            $checklist['question_count'] = (int) $stmt->fetchColumn();
        }

        Response::success($checklists);
    }

    /**
     * Get checklist with questions
     */
    public function getChecklistWithQuestions(int $id): void
    {
        $checklist = $this->checklistModel->withQuestions($id);

        if (!$checklist) {
            Response::error('Checklist not found', 404);
            return;
        }

        Response::success($checklist);
    }

    /**
     * Get all field tours
     */
    public function index(): void
    {
        $tours = $this->fieldTourModel->all();
        Response::success($tours);
    }

    /**
     * Get single field tour
     */
    public function show(int $id): void
    {
        $tour = $this->fieldTourModel->find($id);

        if (!$tour) {
            Response::error('Field tour not found', 404);
            return;
        }

        // Get checklist name
        $checklist = $this->checklistModel->find($tour['checklist_id']);
        $tour['checklist_name'] = $checklist['name'] ?? null;

        Response::success($tour);
    }

    public function store(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['checklist_id'])) {
            Response::error('Checklist ID is required', 422);
            return;
        }

        $userId = AuthMiddleware::getUserId();

        $id = $this->fieldTourModel->create([
            'checklist_id' => $data['checklist_id'],
            'inspector_id' => $userId,
            'location' => $data['location'] ?? null,
            'status' => 'in_progress',
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        $fieldTour = $this->fieldTourModel->find($id);

        Response::success($fieldTour, 'Field tour started', 201);
    }

    /**
     * Save field tour responses
     */
    public function saveResponses(int $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['responses']) || !is_array($data['responses'])) {
            Response::error('Responses array is required', 422);
            return;
        }

        try {
            $saved = 0;
            foreach ($data['responses'] as $response) {
                // Use REPLACE INTO to avoid duplicate key errors
                $sql = "REPLACE INTO field_tour_responses (field_tour_id, question_id, answer, notes, photo) VALUES (?, ?, ?, ?, ?)";
                $stmt = $this->fieldTourModel->db->prepare($sql);
                $stmt->execute([
                    $id,
                    $response['question_id'],
                    $response['answer'] ?? null,
                    $response['notes'] ?? null,
                    $response['photo'] ?? null,
                ]);
                $saved++;
            }

            Response::success([
                'field_tour_id' => $id,
                'responses_saved' => $saved,
            ]);
        } catch (\Exception $e) {
            error_log("Save responses error: " . $e->getMessage());
            Response::error('Failed to save responses: ' . $e->getMessage(), 500);
        }
    }

    public function complete(int $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $this->fieldTourModel->complete($id, [
            'summary' => $data['summary'] ?? null,
            'overall_score' => $data['overall_score'] ?? null,
        ]);

        $fieldTour = $this->fieldTourModel->find($id);

        // Auto-create actions for "no" answers
        $this->createActionsForNoAnswers($id);

        Response::success($fieldTour, 'Field tour completed');
    }

    /**
     * Create actions for "no" answers in field tour
     */
    private function createActionsForNoAnswers(int $tourId): void
    {
        try {
            $db = Database::getConnection();

            // Get field tour info
            $tourStmt = $db->prepare("
                SELECT ft.*, c.name as checklist_name 
                FROM field_tours ft
                LEFT JOIN checklists c ON ft.checklist_id = c.id
                WHERE ft.id = ?
            ");
            $tourStmt->execute([$tourId]);
            $tour = $tourStmt->fetch(PDO::FETCH_ASSOC);

            if (!$tour)
                return;

            // Get all "no" responses with question details
            $stmt = $db->prepare("
                SELECT ftr.*, q.text as question_text
                FROM field_tour_responses ftr
                INNER JOIN checklist_questions q ON ftr.question_id = q.id
                WHERE ftr.field_tour_id = ? AND ftr.answer = 'no'
            ");
            $stmt->execute([$tourId]);
            $noResponses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($noResponses as $response) {
                // Create action for each "no" answer
                $actionCode = 'FT-' . $tourId . '-' . $response['question_id'];

                $actionStmt = $db->prepare("
                    INSERT INTO actions (
                        code,
                        title, 
                        description, 
                        priority, 
                        status,
                        source,
                        assigned_by_user_id,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                $title = "Saha Turu: " . $response['question_text'];
                $description = "Saha Turu Lokasyonu: " . $tour['location'] . "\n";
                $description .= "Checklist: " . $tour['checklist_name'] . "\n";
                $description .= "Soru: " . $response['question_text'] . "\n";
                if ($response['notes']) {
                    $description .= "Notlar: " . $response['notes'] . "\n";
                }
                if ($response['photo']) {
                    $description .= "Fotoğraf: " . $response['photo'];
                }

                $actionStmt->execute([
                    $actionCode,
                    $title,
                    $description,
                    'high', // High priority for "no" answers
                    'open',
                    'field_tour',
                    $tour['inspector_id']
                ]);
            }
        } catch (Exception $e) {
            error_log("Error creating actions for field tour {$tourId}: " . $e->getMessage());
        }
    }
}
