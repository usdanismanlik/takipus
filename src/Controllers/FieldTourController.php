<?php

namespace Src\Controllers;

use Src\Models\FieldTour;
use Src\Models\FieldTourResponse;
use Src\Models\Checklist;
use Src\Models\ChecklistQuestion;
use Src\Models\Action;
use Src\Models\ActionTimeline;
use Src\Models\Notification;
use Src\Helpers\Response;
use Src\Helpers\AuditLogger;
use Src\Helpers\RiskMatrix;
use Src\Services\CoreService;

class FieldTourController
{
    private FieldTour $tourModel;
    private FieldTourResponse $responseModel;
    private Checklist $checklistModel;
    private ChecklistQuestion $questionModel;
    private Action $actionModel;
    private ActionTimeline $timelineModel;
    private Notification $notificationModel;

    public function __construct()
    {
        $this->tourModel = new FieldTour();
        $this->responseModel = new FieldTourResponse();
        $this->checklistModel = new Checklist();
        $this->questionModel = new ChecklistQuestion();
        $this->actionModel = new Action();
        $this->timelineModel = new ActionTimeline();
        $this->notificationModel = new Notification();
    }

    /**
     * POST /api/v1/field-tours
     * Yeni saha turu başlat
     */
    public function start(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        // Validasyon
        if (!isset($data['company_id']) || !isset($data['checklist_id']) || !isset($data['inspector_user_id'])) {
            Response::error('company_id, checklist_id ve inspector_user_id zorunludur', 422);
            return;
        }

        // Checklist var mı ve aktif mi kontrol et
        $checklist = $this->checklistModel->find($data['checklist_id']);
        if (!$checklist) {
            Response::error('Checklist bulunamadı', 404);
            return;
        }

        if ($checklist['status'] !== 'active') {
            Response::error('Sadece aktif checklist\'ler için tur başlatılabilir', 422);
            return;
        }

        // Saha turu oluştur
        $tourId = $this->tourModel->create([
            'company_id' => $data['company_id'],
            'checklist_id' => $data['checklist_id'],
            'inspector_user_id' => $data['inspector_user_id'],
            'location' => $data['location'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'in_progress',
        ]);

        $tour = $this->tourModel->find($tourId);

        // Checklist sorularını da döndür
        $questions = $this->questionModel->getByChecklist($data['checklist_id']);
        $tour['checklist'] = $checklist;
        $tour['questions'] = $questions;

        // Audit log
        AuditLogger::logCreate(
            '/api/v1/field-tours',
            'field_tour',
            $tourId,
            $tour,
            $data['inspector_user_id']
        );

        Response::success($tour, 'Saha turu başlatıldı', 201);
    }

    /**
     * POST /api/v1/field-tours/:id/responses
     * Saha turunda soru cevapla
     */
    public function saveResponse(int $tourId): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        // Tur var mı kontrol et
        $tour = $this->tourModel->find($tourId);
        if (!$tour) {
            Response::error('Saha turu bulunamadı', 404);
            return;
        }

        if ($tour['status'] !== 'in_progress') {
            Response::error('Sadece devam eden turlara cevap eklenebilir', 422);
            return;
        }

        // Validasyon
        if (!isset($data['question_id']) || !isset($data['answer_value'])) {
            Response::error('question_id ve answer_value zorunludur', 422);
            return;
        }

        // Soru var mı kontrol et
        $question = $this->questionModel->find($data['question_id']);
        if (!$question) {
            Response::error('Soru bulunamadı', 404);
            return;
        }

        // is_compliant kontrolü - yes_no tipinde "no" ise uygunsuz
        $isCompliant = 1;
        if ($question['question_type'] === 'yes_no' && strtolower($data['answer_value']) === 'no') {
            $isCompliant = 0;
        } elseif (isset($data['is_compliant'])) {
            $isCompliant = (int) $data['is_compliant'];
        }

        // Fotoğrafları JSON olarak kaydet
        $photos = null;
        if (isset($data['photos'])) {
            if (is_string($data['photos'])) {
                // Frontend'den JSON string olarak geliyorsa direkt kullan
                $photos = $data['photos'];
            } elseif (is_array($data['photos'])) {
                // Array olarak geliyorsa encode et
                $photos = json_encode($data['photos']);
            }
        }

        // Cevabı kaydet
        $responseId = $this->responseModel->create([
            'field_tour_id' => $tourId,
            'question_id' => $data['question_id'],
            'answer_type' => $question['question_type'],
            'answer_value' => $data['answer_value'],
            'is_compliant' => $isCompliant,
            'notes' => $data['notes'] ?? null,
            'photos' => $photos,
            'location' => $data['location'] ?? null,
            'risk_score' => $data['risk_score'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
        ]);

        $response = $this->responseModel->find($responseId);

        // Uygunsuzluk varsa aksiyon oluştur ve bildirim gönder
        if ($isCompliant == 0) {
            $this->createActionForNonCompliance($tour, $question, $response, $data);
        }

        // Audit log
        AuditLogger::logCreate(
            '/api/v1/field-tours/' . $tourId . '/responses',
            'field_tour_response',
            $responseId,
            $response,
            $tour['inspector_user_id']
        );

        Response::success($response, 'Cevap kaydedildi', 201);
    }

    /**
     * Uygunsuzluk için aksiyon oluştur ve bildirimleri gönder
     */
    private function createActionForNonCompliance(array $tour, array $question, array $response, array $data): void
    {
        // Checklist bilgisini al
        $checklist = $this->checklistModel->find($tour['checklist_id']);

        // Aksiyon başlığı ve açıklaması oluştur
        $title = "Uygunsuzluk: " . substr($question['question_text'], 0, 100);
        $description = $question['question_text'] . "\n\n";
        $description .= "Cevap: " . $response['answer_value'] . "\n";
        if ($response['notes']) {
            $description .= "Notlar: " . $response['notes'];
        }

        // Termin uyarı günlerini JSON olarak kaydet
        $reminderDays = null;
        if (isset($data['due_date_reminder_days']) && is_array($data['due_date_reminder_days'])) {
            $reminderDays = json_encode($data['due_date_reminder_days']);
        } elseif (isset($data['due_date'])) {
            // Varsayılan uyarı günleri: 7, 3, 1
            $reminderDays = json_encode([7, 3, 1]);
        }

        // Risk matrisi hesaplama
        $riskProbability = $data['risk_probability'] ?? 3;
        $riskSeverity = $data['risk_severity'] ?? 3;
        $riskInfo = RiskMatrix::calculateRisk($riskProbability, $riskSeverity);

        // Response fotoğraflarını aktar
        $photos = $response['photos'] ?? null;

        // Sorumlu kullanıcıyı belirle
        // 1. Öncelik: data'dan gelen assigned_to_user_id
        // 2. Alternatif: Question'daki responsible_user_ids'den ilk kullanıcı
        $assignedToUserId = $data['assigned_to_user_id'] ?? null;
        if (!$assignedToUserId && !empty($question['responsible_user_ids'])) {
            $responsibleIds = json_decode($question['responsible_user_ids'], true);
            if (is_array($responsibleIds) && !empty($responsibleIds)) {
                $assignedToUserId = $responsibleIds[0];
            }
        }

        // Aksiyonu oluştur
        $actionId = $this->actionModel->create([
            'company_id' => $tour['company_id'],
            'field_tour_id' => $tour['id'],
            'response_id' => $response['id'],
            'title' => $title,
            'description' => $description,
            'photos' => $photos,
            'location' => $response['location'] ?? $tour['location'],
            'assigned_to_user_id' => $assignedToUserId,
            'assigned_to_department_id' => $data['assigned_to_department_id'] ?? null,
            'status' => 'open',
            'priority' => $riskInfo['priority'],
            'risk_score' => $riskInfo['score'],
            'risk_probability' => $riskProbability,
            'risk_severity' => $riskSeverity,
            'risk_level' => $riskInfo['level'],
            'source_type' => 'field_tour',
            'due_date' => $data['due_date'] ?? null,
            'due_date_reminder_days' => $reminderDays,
            'created_by' => $tour['inspector_user_id'],
        ]);

        // Timeline kaydı oluştur
        $metadata = [
            'assigned_to' => CoreService::getUserName($assignedToUserId),
            'due_date' => $data['due_date'] ?? null,
            'risk_level' => $riskInfo['level'],
            'photos' => $photos ? json_decode($photos, true) : null,
            'source' => 'field_tour',
            'field_tour_id' => $tour['id'],
            'response_id' => $response['id']
        ];

        $this->timelineModel->addEvent(
            $actionId,
            'created',
            $tour['inspector_user_id'],
            CoreService::getUserName($tour['inspector_user_id']),
            'Saha turunda uygunsuzluk tespit edildi ve aksiyon oluşturuldu',
            $metadata
        );

        // Bildirimleri oluştur
        $this->createNotifications($checklist, $question, $actionId, $tour);
    }

    /**
     * Bildirim oluştur
     */
    private function createNotifications(array $checklist, array $question, int $actionId, array $tour): void
    {
        $notifiedUsers = [];

        // 1. Checklist genel sorumlusuna bildirim
        if ($checklist['general_responsible_id']) {
            $this->notificationModel->create([
                'user_id' => $checklist['general_responsible_id'],
                'type' => 'checklist_nonconformity',
                'title' => 'Yeni Uygunsuzluk Tespit Edildi',
                'message' => "'{$checklist['name']}' checklist'inde uygunsuzluk tespit edildi.",
                'related_type' => 'action',
                'related_id' => $actionId,
            ]);
            $notifiedUsers[] = $checklist['general_responsible_id'];
        }

        // 2. Soru bazında sorumlu kişilere bildirim
        if ($question['responsible_user_ids']) {
            $responsibleIds = json_decode($question['responsible_user_ids'], true);
            if (is_array($responsibleIds)) {
                foreach ($responsibleIds as $userId) {
                    // Aynı kişiye birden fazla bildirim gönderme
                    if (!in_array($userId, $notifiedUsers)) {
                        $this->notificationModel->create([
                            'user_id' => $userId,
                            'type' => 'action_created',
                            'title' => 'Size Aksiyon Atandı',
                            'message' => "Saha turunda tespit edilen uygunsuzluk için size aksiyon atandı.",
                            'related_type' => 'action',
                            'related_id' => $actionId,
                        ]);
                        $notifiedUsers[] = $userId;
                    }
                }
            }
        }
    }

    /**
     * GET /api/v1/field-tours/:id
     * Saha turu detayı
     */
    public function show(int $id): void
    {
        $tour = $this->tourModel->getWithResponses($id);

        if (!$tour) {
            Response::error('Saha turu bulunamadı', 404);
            return;
        }

        // Checklist bilgisini ekle
        $checklist = $this->checklistModel->withQuestions($tour['checklist_id']);
        $tour['checklist'] = $checklist;

        // Decode JSON fields
        foreach ($tour['responses'] as &$response) {
            if ($response['photos']) {
                $response['photos'] = json_decode($response['photos'], true);
            }
        }

        Response::success($tour);
    }

    /**
     * GET /api/v1/field-tours
     * Saha turlarını listele
     */
    public function index(): void
    {
        $companyId = $_GET['company_id'] ?? null;
        $status = $_GET['status'] ?? null;

        if (!$companyId) {
            Response::error('company_id parametresi zorunludur', 422);
            return;
        }

        $tours = $this->tourModel->getByCompany($companyId, $status);

        // Her tur için cevap sayısını ekle
        foreach ($tours as &$tour) {
            $responses = $this->responseModel->getByTour($tour['id']);
            $tour['response_count'] = count($responses);
            $tour['non_compliant_count'] = count(array_filter($responses, fn($r) => $r['is_compliant'] == 0));
        }

        Response::success($tours);
    }

    /**
     * PUT /api/v1/field-tours/:id/complete
     * Saha turunu tamamla
     */
    public function complete(int $id): void
    {
        $tour = $this->tourModel->find($id);

        if (!$tour) {
            Response::error('Saha turu bulunamadı', 404);
            return;
        }

        if ($tour['status'] !== 'in_progress') {
            Response::error('Sadece devam eden turlar tamamlanabilir', 422);
            return;
        }

        $oldValues = $tour;

        $this->tourModel->update($id, [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
        ]);

        // Audit log
        $updatedTour = $this->tourModel->getWithResponses($id);
        AuditLogger::logUpdate(
            '/api/v1/field-tours/' . $id . '/complete',
            'field_tour',
            $id,
            $oldValues,
            $updatedTour,
            $tour['inspector_user_id']
        );

        Response::success($updatedTour, 'Saha turu tamamlandı');
    }
}
