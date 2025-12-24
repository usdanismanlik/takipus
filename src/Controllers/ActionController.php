<?php

namespace Src\Controllers;

use Src\Models\Action;
use Src\Models\ActionClosure;
use Src\Models\Notification;
use Src\Helpers\Response;
use Src\Helpers\AuditLogger;
use Src\Helpers\RiskMatrix;
use Src\Services\CoreService;

class ActionController
{
    private Action $actionModel;
    private ActionClosure $closureModel;
    private Notification $notificationModel;
    private \PDO $db;

    public function __construct()
    {
        $this->actionModel = new Action();
        $this->closureModel = new ActionClosure();
        $this->notificationModel = new Notification();

        // Database bağlantısını al
        $this->db = $this->actionModel->getDb();
    }

    /**
     * GET /api/v1/actions/form-config?company_id=XXX
     * Form yapılandırması - Mobil uygulama için dinamik form
     */
    public function getFormConfig(): void
    {
        // Company ID zorunlu
        $companyId = $_GET['company_id'] ?? null;
        if (!$companyId) {
            Response::error('company_id parametresi zorunludur', 422);
            return;
        }

        // Firmaya göre özelleştirilebilir config
        // Şimdilik sabit, gelecekte DB'den çekilebilir
        $config = [
            'company_id' => $companyId,
            'fields' => [
                [
                    'name' => 'title',
                    'label' => 'Başlık',
                    'type' => 'text',
                    'required' => true,
                ],
                [
                    'name' => 'description',
                    'label' => 'Açıklama',
                    'type' => 'textarea',
                    'required' => true,
                ],
                [
                    'name' => 'location',
                    'label' => 'Lokasyon',
                    'type' => 'text',
                    'required' => true,
                ],
                [
                    'name' => 'due_date',
                    'label' => 'Termin Tarihi',
                    'type' => 'date',
                    'required' => true,
                ],
                [
                    'name' => 'source_type',
                    'label' => 'Kaynak Tipi',
                    'type' => 'select',
                    'default' => 'manual',
                    'options' => [
                        ['label' => 'Manuel', 'value' => 'manual'],
                        ['label' => 'Saha Turu', 'value' => 'field_tour'],
                        ['label' => 'Periodik Denetim', 'value' => 'periodic_inspection'],
                        ['label' => '3. Taraf Denetim', 'value' => 'third_party_audit']
                    ]
                ],
                [
                    'name' => 'assigned_to_user_id',
                    'label' => 'Atanan Kişi',
                    'type' => 'select',
                    'required' => true,
                    'optionsEndpoint' => 'http://central-auth-and-notification-app.apps.misafirus.com/users/company/{companyId}',
                    'optionsLabelKey' => 'name',
                    'optionsValueKey' => 'id'
                ],
                [
                    'name' => 'risk_probability',
                    'label' => 'Risk Olasılığı (1-5)',
                    'type' => 'select',
                    'required' => true,
                    'default' => '3',
                    'options' => [
                        ['label' => '1 - Çok Nadir', 'value' => '1'],
                        ['label' => '2 - Nadir', 'value' => '2'],
                        ['label' => '3 - Olası', 'value' => '3'],
                        ['label' => '4 - Muhtemel', 'value' => '4'],
                        ['label' => '5 - Çok Muhtemel', 'value' => '5']
                    ]
                ],
                [
                    'name' => 'risk_severity',
                    'label' => 'Risk Şiddeti (1-5)',
                    'type' => 'select',
                    'required' => true,
                    'default' => '3',
                    'options' => [
                        ['label' => '1 - Önemsiz', 'value' => '1'],
                        ['label' => '2 - Hafif', 'value' => '2'],
                        ['label' => '3 - Orta', 'value' => '3'],
                        ['label' => '4 - Ağır', 'value' => '4'],
                        ['label' => '5 - Ölümcül', 'value' => '5']
                    ]
                ],
                [
                    'name' => 'photos',
                    'label' => 'Fotoğraflar',
                    'type' => 'file',
                    'multiple' => true,
                ],
                [
                    'name' => 'requires_supervisor_approval',
                    'label' => 'Üst Yönetici Onayı',
                    'type' => 'boolean',
                    'default' => false,
                ],
                [
                    'name' => 'supervisor_approver_id',
                    'label' => 'Üst Yönetici',
                    'type' => 'select',
                    'dependsOn' => 'requires_supervisor_approval',
                    'optionsEndpoint' => 'http://central-auth-and-notification-app.apps.misafirus.com/users/company/{companyId}',
                    'optionsLabelKey' => 'name',
                    'optionsValueKey' => 'id',
                ]
            ]
        ];

        Response::success($config);
    }

    /**
     * POST /api/v1/actions/manual
     * Manuel aksiyon oluştur
     */
    public function createManual(): void
    {
        // FormData veya JSON destekle
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'multipart/form-data') !== false || strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
            $data = $_POST;
        } else {
            $data = json_decode(file_get_contents('php://input'), true);
        }

        // Validasyon
        if (!isset($data['company_id']) || !isset($data['title']) || !isset($data['description'])) {
            Response::error('company_id, title ve description alanları zorunludur', 422);
            return;
        }

        if (!isset($data['created_by'])) {
            Response::error('created_by alanı zorunludur', 422);
            return;
        }

        // Risk matrisi hesaplama
        $riskProbability = $data['risk_probability'] ?? 3;
        $riskSeverity = $data['risk_severity'] ?? 3;
        $riskInfo = RiskMatrix::calculateRisk($riskProbability, $riskSeverity);

        // Termin uyarı günlerini JSON olarak kaydet
        $reminderDays = null;
        if (isset($data['due_date_reminder_days']) && is_array($data['due_date_reminder_days'])) {
            $reminderDays = json_encode($data['due_date_reminder_days']);
        } elseif (isset($data['due_date'])) {
            $reminderDays = json_encode([7, 3, 1]);
        }

        // Fotoğrafları JSON olarak kaydet
        $photos = null;
        if (isset($data['photos']) && !empty($data['photos'])) {
            if (is_string($data['photos'])) {
                // JSON string olarak gelirse decode et
                $photosArray = json_decode($data['photos'], true);
                if (is_array($photosArray) && !empty($photosArray)) {
                    $photos = json_encode($photosArray);
                }
            } elseif (is_array($data['photos']) && !empty($data['photos'])) {
                $photos = json_encode($data['photos']);
            }
        }

        // Manuel aksiyon oluştur
        $actionId = $this->actionModel->create([
            'company_id' => $data['company_id'],
            'field_tour_id' => null, // Manuel aksiyonlar için null
            'response_id' => null,
            'title' => $data['title'],
            'description' => $data['description'],
            'photos' => $photos,
            'location' => $data['location'] ?? null,
            'assigned_to_user_id' => $data['assigned_to_user_id'] ?? null,
            'assigned_to_department_id' => $data['assigned_to_department_id'] ?? null,
            'upper_approver_id' => $data['upper_approver_id'] ?? null, // Üst amir onayı için
            'status' => 'open',
            'priority' => $riskInfo['priority'],
            'risk_score' => $riskInfo['score'],
            'risk_probability' => $riskProbability,
            'risk_severity' => $riskSeverity,
            'risk_level' => $riskInfo['level'],
            'source_type' => $data['source_type'] ?? 'other',
            'due_date' => $data['due_date'] ?? null,
            'due_date_reminder_days' => $reminderDays,
            'created_by' => $data['created_by'],
        ]);

        // Atanan kişiye bildirim
        if (isset($data['assigned_to_user_id'])) {
            $this->notificationModel->create([
                'user_id' => $data['assigned_to_user_id'],
                'type' => 'action_assigned',
                'title' => 'Yeni Aksiyon Atandı',
                'message' => "Size yeni bir aksiyon atandı: {$data['title']}",
                'related_type' => 'action',
                'related_id' => $actionId,
            ]);
        }

        // Audit log
        $action = $this->actionModel->find($actionId);
        AuditLogger::logCreate(
            '/api/v1/actions/manual',
            'action',
            $actionId,
            $action,
            $data['created_by']
        );

        if ($action['due_date_reminder_days']) {
            $action['due_date_reminder_days'] = json_decode($action['due_date_reminder_days'], true);
        }

        // Photos'u decode et
        if ($action['photos']) {
            $action['photos'] = json_decode($action['photos'], true);
        }

        Response::success($action, 'Manuel aksiyon başarıyla oluşturuldu', 201);
    }

    /**
     * GET /api/v1/actions
     * Aksiyonları listele
     */
    public function index(): void
    {
        $companyId = $_GET['company_id'] ?? null;
        $userId = $_GET['user_id'] ?? null;
        $status = $_GET['status'] ?? null;
        $isOverdue = $_GET['is_overdue'] ?? null;

        if ($userId) {
            $actions = $this->actionModel->getByAssignedUser((int) $userId, $status);
        } elseif ($companyId) {
            $actions = $this->actionModel->getByCompany($companyId, $status);
        } else {
            Response::error('company_id veya user_id parametresi zorunludur', 422);
            return;
        }

        // Overdue filtresi
        if ($isOverdue !== null) {
            $actions = array_filter($actions, function ($action) use ($isOverdue) {
                return $action['is_overdue'] == (int) $isOverdue;
            });
            $actions = array_values($actions);
        }

        // JSON alanlarını decode et ve closure_id ekle
        foreach ($actions as &$action) {
            if ($action['due_date_reminder_days']) {
                $action['due_date_reminder_days'] = json_decode($action['due_date_reminder_days'], true);
            }

            // Tüm aksiyonlar için closure bilgisini ekle
            // Pending approval durumundaysa, closure detaylarını ekle
            if ($action['status'] === 'pending_approval') {
                $closureStmt = $this->db->prepare("
                    SELECT id, status, reviewed_by, upper_approved_by, requires_upper_approval
                    FROM action_closures 
                    WHERE action_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $closureStmt->execute([$action['id']]);
                $closure = $closureStmt->fetch();

                if ($closure) {
                    $action['closure_id'] = $closure['id'];
                    $action['closure_status'] = $closure['status'];
                    $action['closure_requires_upper'] = $closure['requires_upper_approval'];
                    $action['closure_reviewed_by'] = $closure['reviewed_by'];
                    $action['closure_upper_approved_by'] = $closure['upper_approved_by'];
                } else {
                    $action['closure_id'] = null;
                    $action['closure_status'] = null;
                }
            } else {
                $action['closure_id'] = null;
                $action['closure_status'] = null;
            }

            // Photos'u decode et
            if (!empty($action['photos'])) {
                $photos = json_decode($action['photos'], true);
                $action['photos'] = is_array($photos) ? $photos : [];
            } else {
                $action['photos'] = [];
            }
        }

        Response::success($actions);
    }

    /**
     * GET /api/v1/actions/:id
     * Aksiyon detayı
     */
    public function show(int $id): void
    {
        $action = $this->actionModel->find($id);

        if (!$action) {
            Response::error('Aksiyon bulunamadı', 404);
            return;
        }

        // JSON alanlarını decode et
        if ($action['due_date_reminder_days']) {
            $action['due_date_reminder_days'] = json_decode($action['due_date_reminder_days'], true);
        }

        // Aksiyon fotoğraflarını decode et
        if (!empty($action['photos'])) {
            $photos = json_decode($action['photos'], true);
            $action['photos'] = is_array($photos) ? $photos : [];
        } else {
            $action['photos'] = [];
        }

        // Response fotoğraflarını ekle (field tour'dan gelen)
        if ($action['response_id'] && $action['response_id'] > 0) {
            $stmt = $this->db->prepare("SELECT photos FROM field_tour_responses WHERE id = ?");
            $stmt->execute([$action['response_id']]);
            $response = $stmt->fetch();

            if ($response && !empty($response['photos'])) {
                $photos = json_decode($response['photos'], true);
                $action['response_photos'] = is_array($photos) ? $photos : [];
            } else {
                $action['response_photos'] = [];
            }
        } else {
            $action['response_photos'] = [];
        }

        // Closure bilgilerini ekle (kapatma talebi)
        $closure = $this->closureModel->getLatestByAction($id);
        if ($closure) {
            $action['closure_id'] = $closure['id'];
            $action['closure_notes'] = $closure['closure_description'];

            if (!empty($closure['evidence_files'])) {
                $photos = json_decode($closure['evidence_files'], true);
                $action['closure_photos'] = is_array($photos) ? $photos : [];
            } else {
                $action['closure_photos'] = [];
            }

            $action['approval_notes'] = $closure['approval_notes'] ?? null;
            $action['rejection_notes'] = $closure['rejection_notes'] ?? null;
        } else {
            $action['closure_photos'] = [];
            $action['closure_notes'] = null;
            $action['approval_notes'] = null;
            $action['rejection_notes'] = null;
        }

        Response::success($action);
    }

    /**
     * PUT /api/v1/actions/:id
     * Aksiyon güncelle
     */
    public function update(int $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $action = $this->actionModel->find($id);
        if (!$action) {
            Response::error('Aksiyon bulunamadı', 404);
            return;
        }

        $updateData = [];
        $oldStatus = $action['status'];

        if (isset($data['title']))
            $updateData['title'] = $data['title'];
        if (isset($data['description']))
            $updateData['description'] = $data['description'];
        if (isset($data['location']))
            $updateData['location'] = $data['location'];
        if (isset($data['assigned_to_user_id']))
            $updateData['assigned_to_user_id'] = $data['assigned_to_user_id'];
        if (isset($data['assigned_to_department_id']))
            $updateData['assigned_to_department_id'] = $data['assigned_to_department_id'];
        if (isset($data['priority']))
            $updateData['priority'] = $data['priority'];
        if (isset($data['risk_score']))
            $updateData['risk_score'] = $data['risk_score'];
        if (isset($data['status']))
            $updateData['status'] = $data['status'];

        // Due date ve reminder days güncelleme
        if (isset($data['due_date'])) {
            $updateData['due_date'] = !empty($data['due_date']) ? $data['due_date'] : null;
        }

        if (isset($data['due_date_reminder_days']) && is_array($data['due_date_reminder_days'])) {
            $updateData['due_date_reminder_days'] = json_encode($data['due_date_reminder_days']);
        }

        // Risk fields güncelleme
        if (isset($data['risk_probability']) || isset($data['risk_severity'])) {
            $riskProbability = $data['risk_probability'] ?? $action['risk_probability'] ?? 3;
            $riskSeverity = $data['risk_severity'] ?? $action['risk_severity'] ?? 3;

            // Risk matrix hesapla
            $riskInfo = RiskMatrix::calculateRisk($riskProbability, $riskSeverity);

            $updateData['risk_probability'] = $riskProbability;
            $updateData['risk_severity'] = $riskSeverity;
            $updateData['risk_score'] = $riskInfo['score'];
            $updateData['risk_level'] = $riskInfo['level'];
            $updateData['priority'] = $riskInfo['priority'];
        }

        if (!empty($updateData)) {
            // Audit log için eski değerleri kaydet
            $oldValues = $action;

            $this->actionModel->update($id, $updateData);

            // Audit log
            $newValues = $this->actionModel->find($id);
            AuditLogger::logUpdate(
                '/api/v1/actions/' . $id,
                'action',
                $id,
                $oldValues,
                $newValues,
                $action['assigned_to_user_id']
            );
        }

        // Status değişti mi kontrol et
        if (isset($data['status']) && $data['status'] !== $oldStatus) {
            $this->sendStatusChangeNotification($action, $data['status']);
        }

        // Atanan kişi değişti mi kontrol et
        if (isset($data['assigned_to_user_id']) && $data['assigned_to_user_id'] != $action['assigned_to_user_id']) {
            $this->sendAssignmentNotification($action, $data['assigned_to_user_id']);
        }

        $updated = $this->actionModel->find($id);

        // JSON alanlarını decode et
        if ($updated['due_date_reminder_days']) {
            $updated['due_date_reminder_days'] = json_decode($updated['due_date_reminder_days'], true);
        }

        Response::success($updated, 'Aksiyon başarıyla güncellendi');
    }

    /**
     * PUT /api/v1/actions/:id/complete
     * Aksiyonu tamamla
     */
    public function complete(int $id): void
    {
        $action = $this->actionModel->find($id);

        if (!$action) {
            Response::error('Aksiyon bulunamadı', 404);
            return;
        }

        if ($action['status'] === 'completed') {
            Response::error('Aksiyon zaten tamamlanmış', 422);
            return;
        }

        $oldValues = $action;

        $this->actionModel->update($id, [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
        ]);

        // Audit log
        $newValues = $this->actionModel->find($id);
        AuditLogger::logUpdate(
            '/api/v1/actions/' . $id . '/complete',
            'action',
            $id,
            $oldValues,
            $newValues,
            $action['assigned_to_user_id']
        );

        // Tamamlama bildirimi gönder
        $this->sendCompletionNotification($action);

        $updated = $this->actionModel->find($id);
        Response::success($updated, 'Aksiyon tamamlandı');
    }

    /**
     * POST /api/v1/actions/:id/closure-request
     * Kapatma talebi gönder
     */
    public function requestClosure(int $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        // Debug logging
        error_log('=== CLOSURE REQUEST DEBUG ===');
        error_log('Action ID: ' . $id);
        error_log('Request data: ' . json_encode($data));
        error_log('=============================');

        $action = $this->actionModel->find($id);
        if (!$action) {
            Response::error('Aksiyon bulunamadı', 404);
            return;
        }

        // Validasyon
        if (!isset($data['closure_description'])) {
            error_log('ERROR: closure_description missing');
            Response::error('closure_description alanı zorunludur', 422);
            return;
        }

        if (!isset($data['requested_by'])) {
            error_log('ERROR: requested_by missing');
            Response::error('requested_by alanı zorunludur', 422);
            return;
        }

        // Aksiyon zaten tamamlanmış mı?
        if ($action['status'] === 'completed') {
            error_log('ERROR: Action already completed');
            Response::error('Aksiyon zaten tamamlanmış', 422);
            return;
        }

        // Bekleyen kapatma talebi var mı?
        $existingClosure = $this->closureModel->getLatestByAction($id);
        if ($existingClosure && $existingClosure['status'] === 'pending') {
            error_log('ERROR: Pending closure already exists - ' . json_encode($existingClosure));
            Response::error('Bu aksiyon için bekleyen bir kapatma talebi zaten var', 422);
            return;
        }

        // İkinci onayı verecek kişiyi belirle
        $requiresSecondApproval = 0;
        $secondApproverId = $this->getSecondApproverId($action);

        if ($secondApproverId && $secondApproverId > 0) {
            $requiresSecondApproval = 1;
            error_log('Second approval required, approver ID: ' . $secondApproverId);
        } else {
            error_log('No second approval required');
        }

        // Kanıt dosyalarını JSON olarak kaydet
        $evidenceFiles = null;
        if (isset($data['evidence_files']) && !empty($data['evidence_files'])) {
            if (is_string($data['evidence_files'])) {
                // JSON string olarak gelirse decode et
                $filesArray = json_decode($data['evidence_files'], true);
                if (is_array($filesArray) && !empty($filesArray)) {
                    $evidenceFiles = json_encode($filesArray);
                }
            } elseif (is_array($data['evidence_files']) && !empty($data['evidence_files'])) {
                $evidenceFiles = json_encode($data['evidence_files']);
            }
        }

        // Kapatma talebi oluştur
        $closureId = $this->closureModel->create([
            'action_id' => $id,
            'requested_by' => $data['requested_by'],
            'closure_description' => $data['closure_description'],
            'evidence_files' => $evidenceFiles,
            'requires_upper_approval' => $requiresSecondApproval,
            'status' => 'pending',
        ]);

        // Aksiyonun durumunu 'pending_approval' yap
        $this->actionModel->update($id, ['status' => 'pending_approval']);

        // Aksiyonu oluşturan kişiye bildirim gönder
        $this->sendClosureRequestToCreator($action, $closureId);

        // Audit log
        $closure = $this->closureModel->find($closureId);
        AuditLogger::logCreate(
            '/api/v1/actions/' . $id . '/closure-request',
            'action_closure',
            $closureId,
            $closure,
            $data['requested_by']
        );

        if ($closure['evidence_files']) {
            $closure['evidence_files'] = json_decode($closure['evidence_files'], true);
        }

        Response::success($closure, 'Kapatma talebi gönderildi', 201);
    }

    /**
     * PUT /api/v1/actions/:id/closure/:closureId/approve
     * Kapatma talebini onayla
     */
    public function approveClosure(int $id, int $closureId): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $closure = $this->closureModel->find($closureId);
        if (!$closure || $closure['action_id'] != $id) {
            Response::error('Kapatma talebi bulunamadı', 404);
            return;
        }

        if (!isset($data['reviewed_by'])) {
            Response::error('reviewed_by alanı zorunludur', 422);
            return;
        }

        $action = $this->actionModel->find($id);
        $reviewedBy = $data['reviewed_by'];

        // İlk onay mı, ikinci onay mı?
        if ($closure['status'] === 'pending') {
            // İlk onay - Creator tarafından yapılmalı
            if ($reviewedBy != $action['created_by']) {
                Response::error('İlk onay sadece aksiyonu oluşturan kişi tarafından yapılabilir', 403);
                return;
            }

            if ($closure['requires_upper_approval'] == 1) {
                // İkinci onay gerekli
                $this->closureModel->update($closureId, [
                    'reviewed_by' => $reviewedBy,
                    'review_notes' => $data['review_notes'] ?? null,
                    'reviewed_at' => date('Y-m-d H:i:s'),
                    'status' => 'first_approved',
                ]);

                // İkinci onayı verecek kişiye bildirim gönder
                $this->sendSecondApprovalRequest($action, $closureId);

                $message = 'İlk onay tamamlandı. İkinci onay bekleniyor.';
            } else {
                // İkinci onay gerekmez, direkt tamamla
                $this->closureModel->update($closureId, [
                    'reviewed_by' => $reviewedBy,
                    'review_notes' => $data['review_notes'] ?? null,
                    'reviewed_at' => date('Y-m-d H:i:s'),
                    'status' => 'approved',
                ]);

                // Aksiyonu tamamla
                $this->actionModel->update($id, [
                    'status' => 'completed',
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);

                $message = 'Kapatma talebi onaylandı ve aksiyon tamamlandı.';
            }

        } else if ($closure['status'] === 'first_approved') {
            // İkinci onay - Yetkili kişi tarafından yapılmalı
            $secondApproverId = $this->getSecondApproverId($action);

            if (!$secondApproverId || $reviewedBy != $secondApproverId) {
                Response::error('İkinci onay sadece yetkili kişi tarafından yapılabilir', 403);
                return;
            }

            $this->closureModel->update($closureId, [
                'upper_approved_by' => $reviewedBy,
                'upper_review_notes' => $data['review_notes'] ?? null,
                'upper_reviewed_at' => date('Y-m-d H:i:s'),
                'status' => 'approved',
            ]);

            // Aksiyonu tamamla
            $this->actionModel->update($id, [
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s'),
            ]);

            $message = 'İkinci onay tamamlandı ve aksiyon kapandı.';
        } else {
            Response::error('Bu kapatma talebi zaten işleme alınmış', 422);
            return;
        }

        // Audit log
        $updatedClosure = $this->closureModel->find($closureId);
        AuditLogger::logUpdate(
            '/api/v1/actions/' . $id . '/closure/' . $closureId . '/approve',
            'action_closure',
            $closureId,
            $closure,
            $updatedClosure,
            $data['reviewed_by']
        );

        if ($updatedClosure['evidence_files']) {
            $updatedClosure['evidence_files'] = json_decode($updatedClosure['evidence_files'], true);
        }

        Response::success($updatedClosure, $message);
    }

    /**
     * PUT /api/v1/actions/:id/closure/:closureId/reject
     * Kapatma talebini reddet
     */
    public function rejectClosure(int $id, int $closureId): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $closure = $this->closureModel->find($closureId);
        if (!$closure || $closure['action_id'] != $id) {
            Response::error('Kapatma talebi bulunamadı', 404);
            return;
        }

        if ($closure['status'] !== 'pending') {
            Response::error('Bu kapatma talebi zaten işleme alınmış', 422);
            return;
        }

        if (!isset($data['reviewed_by']) || !isset($data['review_notes'])) {
            Response::error('reviewed_by ve review_notes alanları zorunludur', 422);
            return;
        }

        // Reddet
        $this->closureModel->update($closureId, [
            'reviewed_by' => $data['reviewed_by'],
            'review_notes' => $data['review_notes'],
            'reviewed_at' => date('Y-m-d H:i:s'),
            'status' => 'rejected',
        ]);

        // Aksiyonun durumunu geri al
        $this->actionModel->update($id, ['status' => 'in_progress']);

        // Reddedilme bildirimi
        // TODO: Bildirim servisi implement edilecek
        // $action = $this->actionModel->find($id);
        // $this->sendClosureRejectedNotifications($action, $closure, $data['review_notes']);

        // Audit log
        $updatedClosure = $this->closureModel->find($closureId);
        AuditLogger::logUpdate(
            '/api/v1/actions/' . $id . '/closure/' . $closureId . '/reject',
            'action_closure',
            $closureId,
            $closure,
            $updatedClosure,
            $data['reviewed_by']
        );

        if ($updatedClosure['evidence_files']) {
            $updatedClosure['evidence_files'] = json_decode($updatedClosure['evidence_files'], true);
        }

        Response::success($updatedClosure, 'Kapatma talebi reddedildi');
    }

    /**
     * GET /api/v1/actions/:id/closures
     * Aksiyonun kapatma taleplerini listele
     */
    public function getClosures(int $id): void
    {
        $action = $this->actionModel->find($id);
        if (!$action) {
            Response::error('Aksiyon bulunamadı', 404);
            return;
        }

        $closures = $this->closureModel->getByAction($id);

        // JSON alanlarını decode et
        foreach ($closures as &$closure) {
            if ($closure['evidence_files']) {
                $closure['evidence_files'] = json_decode($closure['evidence_files'], true);
            }
        }

        Response::success($closures);
    }

    /**
     * Status değişikliği bildirimi
     */
    private function sendStatusChangeNotification(array $action, string $newStatus): void
    {
        if ($action['assigned_to_user_id']) {
            $statusLabels = [
                'open' => 'Açık',
                'in_progress' => 'Devam Ediyor',
                'pending_approval' => 'Onay Bekliyor',
                'completed' => 'Tamamlandı',
                'cancelled' => 'İptal Edildi',
            ];

            $this->notificationModel->create([
                'user_id' => $action['assigned_to_user_id'],
                'type' => 'action_status_changed',
                'title' => 'Aksiyon Durumu Değişti',
                'message' => "'{$action['title']}' aksiyonunun durumu '{$statusLabels[$newStatus]}' olarak güncellendi.",
                'related_type' => 'action',
                'related_id' => $action['id'],
            ]);

            // Core Service Trigger
            CoreService::sendPushNotification(
                (int) $action['assigned_to_user_id'],
                'Aksiyon Durumu Değişti',
                "'{$action['title']}' durumu '{$statusLabels[$newStatus]}' oldu.",
                ['action_id' => $action['id'], 'type' => 'action_status_changed']
            );
        }
    }

    /**
     * Atama bildirimi
     */
    private function sendAssignmentNotification(array $action, int $newUserId): void
    {
        $this->notificationModel->create([
            'user_id' => $newUserId,
            'type' => 'action_assigned',
            'title' => 'Yeni Aksiyon Atandı',
            'message' => "Size yeni bir aksiyon atandı: {$action['title']}",
            'related_type' => 'action',
            'related_id' => $action['id'],
        ]);

        // Core Service Trigger
        CoreService::sendPushNotification(
            $newUserId,
            'Yeni Aksiyon Atandı',
            "Size yeni bir aksiyon atandı: {$action['title']}",
            ['action_id' => $action['id'], 'type' => 'action_assigned']
        );
    }

    /**
     * Tamamlama bildirimi
     */
    private function sendCompletionNotification(array $action): void
    {
        // Aksiyonu oluşturana bildirim gönder
        if ($action['created_by']) {
            $this->notificationModel->create([
                'user_id' => $action['created_by'],
                'type' => 'action_completed',
                'title' => 'Aksiyon Tamamlandı',
                'message' => "'{$action['title']}' aksiyonu tamamlandı.",
                'related_type' => 'action',
                'related_id' => $action['id'],
            ]);

            // Core Service Trigger
            CoreService::sendPushNotification(
                (int) $action['created_by'],
                'Aksiyon Tamamlandı',
                "'{$action['title']}' aksiyonu tamamlandı.",
                ['action_id' => $action['id'], 'type' => 'action_completed']
            );
        }
    }

    /**
     * İkinci onayı verecek kişiyi belirler
     * Field tour aksiyonlarında checklist responsible
     * Manuel aksiyonlarda upper_approver_id
     */
    private function getSecondApproverId(array $action): ?int
    {
        // Field tour aksiyonu mu?
        if ($action['field_tour_id']) {
            $stmt = $this->db->prepare("
                SELECT c.general_responsible_id 
                FROM field_tours ft
                JOIN checklists c ON ft.checklist_id = c.id
                WHERE ft.id = ?
            ");
            $stmt->execute([$action['field_tour_id']]);
            $result = $stmt->fetch();

            if ($result && $result['general_responsible_id'] > 0) {
                return (int) $result['general_responsible_id'];
            }
            return null;
        }

        // Manuel aksiyon - upper_approver_id kullan
        if (isset($action['upper_approver_id']) && $action['upper_approver_id'] > 0) {
            return (int) $action['upper_approver_id'];
        }

        return null;
    }

    /**
     * Kapatma talebini aksiyonu oluşturana bildirir
     */
    private function sendClosureRequestToCreator(array $action, int $closureId): void
    {
        if (!$action['created_by']) {
            return;
        }

        $this->notificationModel->create([
            'user_id' => $action['created_by'],
            'type' => 'action_status_changed',
            'title' => 'Kapatma Talebi Onayınızı Bekliyor',
            'message' => "'{$action['title']}' aksiyonu için kapatma talebi gönderildi. Lütfen onaylayın.",
            'related_type' => 'action',
            'related_id' => $action['id'],
        ]);

        CoreService::sendPushNotification(
            (int) $action['created_by'],
            'Kapatma Talebi',
            "'{$action['title']}' için kapatma talebi onayınızı bekliyor",
            ['action_id' => $action['id'], 'closure_id' => $closureId, 'type' => 'closure_pending']
        );
    }

    /**
     * İkinci onay gerektiğinde yetkili kişiye bildirim gönderir
     */
    private function sendSecondApprovalRequest(array $action, int $closureId): void
    {
        $secondApproverId = $this->getSecondApproverId($action);

        if (!$secondApproverId) {
            return;
        }

        $this->notificationModel->create([
            'user_id' => $secondApproverId,
            'type' => 'action_status_changed',
            'title' => 'İkinci Onay Gerekli',
            'message' => "'{$action['title']}' aksiyonu için kapatma talebi ikinci onayınızı bekliyor.",
            'related_type' => 'action',
            'related_id' => $action['id'],
        ]);

        CoreService::sendPushNotification(
            $secondApproverId,
            'İkinci Onay Gerekli',
            "'{$action['title']}' kapatma talebi onayınızı bekliyor",
            ['action_id' => $action['id'], 'closure_id' => $closureId, 'type' => 'closure_second_approval']
        );
    }
}
