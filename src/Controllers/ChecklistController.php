<?php

namespace Src\Controllers;

use Src\Models\Checklist;
use Src\Models\ChecklistQuestion;
use Src\Helpers\Response;
use Src\Helpers\AuditLogger;

class ChecklistController
{
    private Checklist $checklistModel;
    private ChecklistQuestion $questionModel;

    public function __construct()
    {
        $this->checklistModel = new Checklist();
        $this->questionModel = new ChecklistQuestion();
    }

    /**
     * GET /api/v1/checklists
     * Tüm checklist'leri listele (opsiyonel: company_id ve status filtresi)
     */
    public function index(): void
    {
        $companyId = $_GET['company_id'] ?? null;
        $status = $_GET['status'] ?? null;

        if ($companyId) {
            $checklists = $this->checklistModel->getByCompany($companyId, $status);
        } else {
            $conditions = [];
            if ($status) {
                $conditions['status'] = $status;
            }
            $checklists = $this->checklistModel->all($conditions);
        }

        // Her checklist için soru sayısını ekle
        foreach ($checklists as &$checklist) {
            $questions = $this->questionModel->getByChecklist($checklist['id']);
            $checklist['question_count'] = count($questions);
        }

        Response::success($checklists);
    }

    /**
     * GET /api/v1/checklists/:id
     * Belirli bir checklist'i sorularıyla birlikte getir
     */
    public function show(int $id): void
    {
        $checklist = $this->checklistModel->withQuestions($id);

        if (!$checklist) {
            Response::error('Checklist bulunamadı', 404);
            return;
        }

        Response::success($checklist);
    }

    /**
     * GET /api/v1/companies/:companyId/checklists
     * Belirli bir firmaya ait checklist'leri listele
     */
    public function getByCompany(string $companyId): void
    {
        $status = $_GET['status'] ?? null;
        $checklists = $this->checklistModel->getByCompany($companyId, $status);

        // Her checklist için soru sayısını ekle
        foreach ($checklists as &$checklist) {
            $questions = $this->questionModel->getByChecklist($checklist['id']);
            $checklist['question_count'] = count($questions);
        }

        Response::success([
            'company_id' => $companyId,
            'checklists' => $checklists,
            'total' => count($checklists)
        ]);
    }

    /**
     * POST /api/v1/checklists
     * Yeni checklist oluştur
     */
    public function store(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        // Validasyon
        if (!isset($data['company_id']) || !isset($data['name'])) {
            Response::error('company_id ve name alanları zorunludur', 422);
            return;
        }

        // Checklist oluştur
        $checklistId = $this->checklistModel->create([
            'company_id' => $data['company_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'general_responsible_id' => $data['general_responsible_id'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);

        // Sorular varsa ekle
        if (isset($data['questions']) && is_array($data['questions'])) {
            foreach ($data['questions'] as $question) {
                if (!isset($question['question_text']) || !isset($question['question_type'])) {
                    continue;
                }

                // Skor tipi için min/max kontrol (yoksa default değerler)
                if ($question['question_type'] === 'score') {
                    if (!isset($question['min_score'])) {
                        $question['min_score'] = 1;
                    }
                    if (!isset($question['max_score'])) {
                        $question['max_score'] = 10;
                    }
                }

                // Sorumlu kullanıcıları JSON olarak kaydet
                $responsibleUserIds = null;
                if (isset($question['responsible_user_ids']) && is_array($question['responsible_user_ids']) && !empty($question['responsible_user_ids'])) {
                    $responsibleUserIds = json_encode($question['responsible_user_ids']);
                } elseif (isset($question['responsible_user_id']) && !empty($question['responsible_user_id'])) {
                    // Tek sorumlu varsa ve boş değilse array'e çevir
                    $responsibleUserIds = json_encode([$question['responsible_user_id']]);
                }

                $this->questionModel->create([
                    'checklist_id' => $checklistId,
                    'order_num' => $question['order_num'] ?? 1,
                    'question_text' => $question['question_text'],
                    'question_type' => $question['question_type'],
                    'is_required' => isset($question['is_required']) ? (int) $question['is_required'] : 1,
                    'photo_required' => isset($question['photo_required']) ? (int) $question['photo_required'] : 0,
                    'help_text' => $question['help_text'] ?? null,
                    'min_score' => $question['min_score'] ?? null,
                    'max_score' => $question['max_score'] ?? null,
                    'responsible_user_ids' => $responsibleUserIds,
                ]);
            }
        }

        $checklist = $this->checklistModel->withQuestions($checklistId);

        // Audit log
        AuditLogger::logCreate(
            '/api/v1/checklists',
            'checklist',
            $checklistId,
            $checklist,
            $data['created_by'] ?? null
        );

        Response::success($checklist, 'Checklist başarıyla oluşturuldu', 201);
    }

    /**
     * PUT /api/v1/checklists/:id
     * Mevcut checklist'i güncelle
     */
    public function update(int $id): void
    {
        // FormData veya JSON parse et
        $data = $_POST ?: json_decode(file_get_contents('php://input'), true);

        // Debug
        error_log("UPDATE CHECKLIST - Data source: " . ($_POST ? 'FormData' : 'JSON'));
        if (isset($data['questions'])) {
            error_log("Questions count: " . count($data['questions']));
            foreach ($data['questions'] as $idx => $q) {
                error_log("Q[$idx] responsible_user_id: " . ($q['responsible_user_id'] ?? 'not set'));
            }
        }

        // Checklist var mı kontrol et
        $checklist = $this->checklistModel->find($id);
        if (!$checklist) {
            Response::error('Checklist bulunamadı', 404);
            return;
        }

        // Checklist bilgilerini güncelle
        $updateData = [];
        if (isset($data['name']))
            $updateData['name'] = $data['name'];
        if (isset($data['description']))
            $updateData['description'] = $data['description'];
        if (isset($data['status']))
            $updateData['status'] = $data['status'];
        if (isset($data['company_id']))
            $updateData['company_id'] = $data['company_id'];
        if (isset($data['general_responsible_id']))
            $updateData['general_responsible_id'] = $data['general_responsible_id'];

        if (!empty($updateData)) {
            $this->checklistModel->update($id, $updateData);
        }

        // Sorular güncellenecekse
        if (isset($data['questions']) && is_array($data['questions'])) {
            // Mevcut soruları al
            $existingQuestions = $this->questionModel->getByChecklist($id);
            $existingQuestionIds = array_column($existingQuestions, 'id');
            $updatedQuestionIds = [];

            // Gelen soruları işle (UPDATE veya INSERT)
            foreach ($data['questions'] as $question) {
                if (!isset($question['question_text']) || !isset($question['question_type'])) {
                    continue;
                }


                // Skor tipi için min/max kontrol (yoksa default değerler)
                if ($question['question_type'] === 'score') {
                    if (!isset($question['min_score'])) {
                        $question['min_score'] = 1;
                    }
                    if (!isset($question['max_score'])) {
                        $question['max_score'] = 10;
                    }
                }

                // Sorumlu kullanıcıları JSON olarak kaydet
                $responsibleUserIds = null;
                if (isset($question['responsible_user_ids']) && is_array($question['responsible_user_ids']) && !empty($question['responsible_user_ids'])) {
                    $responsibleUserIds = json_encode($question['responsible_user_ids']);
                } elseif (isset($question['responsible_user_id']) && !empty($question['responsible_user_id'])) {
                    // Tek sorumlu varsa ve boş değilse array'e çevir
                    $responsibleUserIds = json_encode([$question['responsible_user_id']]);
                }

                $questionData = [
                    'checklist_id' => $id,
                    'order_num' => $question['order_num'] ?? 1,
                    'question_text' => $question['question_text'],
                    'question_type' => $question['question_type'],
                    'is_required' => isset($question['is_required']) ? (int) $question['is_required'] : 1,
                    'photo_required' => isset($question['photo_required']) ? (int) $question['photo_required'] : 0,
                    'help_text' => $question['help_text'] ?? null,
                    'min_score' => $question['min_score'] ?? null,
                    'max_score' => $question['max_score'] ?? null,
                    'responsible_user_ids' => $responsibleUserIds,
                ];

                // Eğer question ID varsa UPDATE, yoksa INSERT
                if (isset($question['id']) && in_array($question['id'], $existingQuestionIds)) {
                    // Mevcut soruyu güncelle
                    $this->questionModel->update($question['id'], $questionData);
                    $updatedQuestionIds[] = $question['id'];
                } else {
                    // Yeni soru ekle
                    $newQuestionId = $this->questionModel->create($questionData);
                    $updatedQuestionIds[] = $newQuestionId;
                }
            }

            // Silinmesi gereken soruları bul (gelen listede olmayan mevcut sorular)
            // NOT: Sadece field_tour_responses'ta referansı OLMAYAN soruları sil
            $questionsToDelete = array_diff($existingQuestionIds, $updatedQuestionIds);
            foreach ($questionsToDelete as $questionId) {
                // Önce bu soruya ait response var mı kontrol et
                $hasResponses = $this->questionModel->hasResponses($questionId);
                if (!$hasResponses) {
                    // Response yoksa güvenle sil
                    $this->questionModel->delete($questionId);
                }
                // Response varsa silme, sadece bırak (soft delete alternatifi)
            }
        }


        $updatedChecklist = $this->checklistModel->withQuestions($id);

        // Audit log
        AuditLogger::logUpdate(
            '/api/v1/checklists/' . $id,
            'checklist',
            $id,
            $checklist,
            $updatedChecklist,
            $checklist['created_by']
        );

        Response::success($updatedChecklist, 'Checklist başarıyla güncellendi');
    }

    /**
     * DELETE /api/v1/checklists/:id
     * Checklist'i sil (soft delete - status'u archived yap)
     */
    public function destroy(int $id): void
    {
        $checklist = $this->checklistModel->find($id);
        if (!$checklist) {
            Response::error('Checklist bulunamadı', 404);
            return;
        }

        // Soft delete - status'u archived yap
        $oldValues = $checklist;
        $this->checklistModel->update($id, ['status' => 'archived']);

        // Audit log
        AuditLogger::logUpdate(
            '/api/v1/checklists/' . $id,
            'checklist',
            $id,
            $oldValues,
            ['status' => 'archived'],
            $checklist['created_by']
        );

        Response::success(null, 'Checklist arşivlendi');
    }
}
