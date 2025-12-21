<?php

namespace Src\Controllers;

use Src\Models\Action;
use Src\Models\ActionClosure;
use Src\Models\Notification;
use Src\Helpers\Response;
use Src\Helpers\AuditLogger;
use Src\Helpers\RiskMatrix;

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
     * POST /api/v1/actions/manual
     * Manuel aksiyon oluştur
     */
    public function createManual(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

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

            // Photos'u decode et
            if (!empty($action['photos'])) {
                $photos = json_decode($action['photos'], true);
                $action['photos'] = is_array($photos) ? $photos : [];
            } else {
                $action['photos'] = [];
            }

            // Closure ID'yi ekle
            $closure = $this->closureModel->getLatestByAction($action['id']);
            $action['closure_id'] = $closure ? $closure['id'] : null;
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
            $updateData['due_date'] = $data['due_date'];
        }

        if (isset($data['due_date_reminder_days']) && is_array($data['due_date_reminder_days'])) {
            $updateData['due_date_reminder_days'] = json_encode($data['due_date_reminder_days']);
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

        $action = $this->actionModel->find($id);
        if (!$action) {
            Response::error('Aksiyon bulunamadı', 404);
            return;
        }

        // Validasyon
        if (!isset($data['closure_description'])) {
            Response::error('closure_description alanı zorunludur', 422);
            return;
        }

        if (!isset($data['requested_by'])) {
            Response::error('requested_by alanı zorunludur', 422);
            return;
        }

        // Aksiyon zaten tamamlanmış mı?
        if ($action['status'] === 'completed') {
            Response::error('Aksiyon zaten tamamlanmış', 422);
            return;
        }

        // Bekleyen kapatma talebi var mı?
        $existingClosure = $this->closureModel->getLatestByAction($id);
        if ($existingClosure && $existingClosure['status'] === 'pending') {
            Response::error('Bu aksiyon için bekleyen bir kapatma talebi zaten var', 422);
            return;
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
            'requires_upper_approval' => $data['requires_upper_approval'] ?? 0,
            'status' => 'pending',
        ]);

        // Aksiyonun durumunu 'pending_approval' yap
        $this->actionModel->update($id, ['status' => 'pending_approval']);

        // TODO: Bildirimleri gönder (NotificationService implement edilecek)
        // $this->sendClosureRequestNotifications($action, $closureId);

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

        if ($closure['status'] !== 'pending') {
            Response::error('Bu kapatma talebi zaten işleme alınmış', 422);
            return;
        }

        if (!isset($data['reviewed_by'])) {
            Response::error('reviewed_by alanı zorunludur', 422);
            return;
        }

        $isUpperApproval = $data['is_upper_approval'] ?? false;

        if ($isUpperApproval) {
            // Üst amir onayı
            $this->closureModel->update($closureId, [
                'upper_approved_by' => $data['reviewed_by'],
                'upper_review_notes' => $data['review_notes'] ?? null,
                'upper_reviewed_at' => date('Y-m-d H:i:s'),
                'status' => 'approved',
            ]);
        } else {
            // Normal onay
            $updateData = [
                'reviewed_by' => $data['reviewed_by'],
                'review_notes' => $data['review_notes'] ?? null,
                'reviewed_at' => date('Y-m-d H:i:s'),
            ];

            // Üst amir onayı gerekli mi?
            if ($closure['requires_upper_approval']) {
                // Henüz tamamlanmadı, üst amir onayı bekliyor
                $updateData['status'] = 'pending';
            } else {
                // Onaylanmış, aksiyon tamamlanabilir
                $updateData['status'] = 'approved';
            }

            $this->closureModel->update($closureId, $updateData);
        }

        $updatedClosure = $this->closureModel->find($closureId);

        // Eğer tam onay aldıysa aksiyonu tamamla
        if ($updatedClosure['status'] === 'approved') {
            $this->actionModel->update($id, [
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s'),
            ]);

            // Tamamlama bildirimi
            // TODO: Bildirim servisi implement edilecek
            // $action = $this->actionModel->find($id);
            // $this->sendClosureApprovedNotifications($action, $updatedClosure);
        } elseif ($closure['requires_upper_approval'] && !$isUpperApproval) {
            // Üst amir onayı için bildirim gönder
            // TODO: Bildirim servisi implement edilecek
            // $this->sendUpperApprovalRequestNotification($id, $closureId);
        }

        // Audit log
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

        Response::success($updatedClosure, 'Kapatma talebi onaylandı');
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
        }
    }
}
