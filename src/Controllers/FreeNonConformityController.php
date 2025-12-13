<?php

namespace Src\Controllers;

use Src\Models\FreeNonConformity;
use Src\Models\Notification;
use Src\Helpers\Response;
use Src\Helpers\AuditLogger;

class FreeNonConformityController
{
    private FreeNonConformity $nonConformityModel;
    private Notification $notificationModel;

    public function __construct()
    {
        $this->nonConformityModel = new FreeNonConformity();
        $this->notificationModel = new Notification();
    }

    /**
     * POST /api/v1/free-nonconformities
     * Serbest uygunsuzluk ekle
     */
    public function store(): void
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

        // Sorumlu kullanıcıları JSON olarak kaydet
        $assignedUserIds = null;
        if (isset($data['assigned_to_user_ids']) && is_array($data['assigned_to_user_ids'])) {
            $assignedUserIds = json_encode($data['assigned_to_user_ids']);
        }

        // Fotoğrafları JSON olarak kaydet
        $photos = null;
        if (isset($data['photos']) && is_array($data['photos'])) {
            $photos = json_encode($data['photos']);
        }

        // Uygunsuzluk oluştur
        $nonConformityId = $this->nonConformityModel->create([
            'company_id' => $data['company_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'location' => $data['location'] ?? null,
            'assigned_to_user_ids' => $assignedUserIds,
            'priority' => $data['priority'] ?? 'medium',
            'risk_score' => $data['risk_score'] ?? null,
            'photos' => $photos,
            'status' => $data['status'] ?? 'open',
            'due_date' => $data['due_date'] ?? null,
            'created_by' => $data['created_by'],
        ]);

        // Bildirimleri oluştur
        if (isset($data['assigned_to_user_ids']) && is_array($data['assigned_to_user_ids'])) {
            foreach ($data['assigned_to_user_ids'] as $userId) {
                $this->notificationModel->create([
                    'user_id' => $userId,
                    'type' => 'action_assigned',
                    'title' => 'Yeni Uygunsuzluk Atandı',
                    'message' => "Size yeni bir uygunsuzluk atandı: {$data['title']}",
                    'related_type' => 'action',
                    'related_id' => $nonConformityId,
                ]);
            }
        }

        $nonConformity = $this->nonConformityModel->find($nonConformityId);
        
        // JSON alanlarını decode et
        if ($nonConformity['assigned_to_user_ids']) {
            $nonConformity['assigned_to_user_ids'] = json_decode($nonConformity['assigned_to_user_ids'], true);
        }
        if ($nonConformity['photos']) {
            $nonConformity['photos'] = json_decode($nonConformity['photos'], true);
        }
        
        // Audit log
        AuditLogger::logCreate(
            '/api/v1/free-nonconformities',
            'free_nonconformity',
            $nonConformityId,
            $nonConformity,
            $data['created_by']
        );

        Response::success($nonConformity, 'Uygunsuzluk başarıyla oluşturuldu', 201);
    }

    /**
     * GET /api/v1/free-nonconformities
     * Serbest uygunsuzlukları listele
     */
    public function index(): void
    {
        $companyId = $_GET['company_id'] ?? null;
        $status = $_GET['status'] ?? null;
        $userId = $_GET['user_id'] ?? null;

        if ($userId) {
            // Kullanıcıya atanan uygunsuzluklar
            $nonConformities = $this->nonConformityModel->getByAssignedUser((int)$userId, $status);
        } elseif ($companyId) {
            // Firmaya ait uygunsuzluklar
            $nonConformities = $this->nonConformityModel->getByCompany($companyId, $status);
        } else {
            Response::error('company_id veya user_id parametresi zorunludur', 422);
            return;
        }

        // JSON alanlarını decode et
        foreach ($nonConformities as &$item) {
            if ($item['assigned_to_user_ids']) {
                $item['assigned_to_user_ids'] = json_decode($item['assigned_to_user_ids'], true);
            }
            if ($item['photos']) {
                $item['photos'] = json_decode($item['photos'], true);
            }
        }

        Response::success($nonConformities);
    }

    /**
     * GET /api/v1/free-nonconformities/:id
     * Tek uygunsuzluk detayı
     */
    public function show(int $id): void
    {
        $nonConformity = $this->nonConformityModel->find($id);

        if (!$nonConformity) {
            Response::error('Uygunsuzluk bulunamadı', 404);
            return;
        }

        // JSON alanlarını decode et
        if ($nonConformity['assigned_to_user_ids']) {
            $nonConformity['assigned_to_user_ids'] = json_decode($nonConformity['assigned_to_user_ids'], true);
        }
        if ($nonConformity['photos']) {
            $nonConformity['photos'] = json_decode($nonConformity['photos'], true);
        }

        Response::success($nonConformity);
    }

    /**
     * PUT /api/v1/free-nonconformities/:id
     * Uygunsuzluğu güncelle
     */
    public function update(int $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $nonConformity = $this->nonConformityModel->find($id);
        if (!$nonConformity) {
            Response::error('Uygunsuzluk bulunamadı', 404);
            return;
        }

        $updateData = [];
        if (isset($data['title'])) $updateData['title'] = $data['title'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['location'])) $updateData['location'] = $data['location'];
        if (isset($data['priority'])) $updateData['priority'] = $data['priority'];
        if (isset($data['risk_score'])) $updateData['risk_score'] = $data['risk_score'];
        if (isset($data['status'])) $updateData['status'] = $data['status'];
        if (isset($data['due_date'])) $updateData['due_date'] = $data['due_date'];

        // Sorumlu kullanıcılar güncelleniyorsa
        if (isset($data['assigned_to_user_ids']) && is_array($data['assigned_to_user_ids'])) {
            $updateData['assigned_to_user_ids'] = json_encode($data['assigned_to_user_ids']);
        }

        // Fotoğraflar güncelleniyorsa
        if (isset($data['photos']) && is_array($data['photos'])) {
            $updateData['photos'] = json_encode($data['photos']);
        }

        if (!empty($updateData)) {
            $oldValues = $nonConformity;
            $this->nonConformityModel->update($id, $updateData);
            
            // Audit log
            $updated = $this->nonConformityModel->find($id);
            AuditLogger::logUpdate(
                '/api/v1/free-nonconformities/' . $id,
                'free_nonconformity',
                $id,
                $oldValues,
                $updated,
                $nonConformity['created_by']
            );
        } else {
            $updated = $this->nonConformityModel->find($id);
        }

        $updated = $this->nonConformityModel->find($id);
        
        // JSON alanlarını decode et
        if ($updated['assigned_to_user_ids']) {
            $updated['assigned_to_user_ids'] = json_decode($updated['assigned_to_user_ids'], true);
        }
        if ($updated['photos']) {
            $updated['photos'] = json_decode($updated['photos'], true);
        }

        Response::success($updated, 'Uygunsuzluk başarıyla güncellendi');
    }

    /**
     * DELETE /api/v1/free-nonconformities/:id
     * Uygunsuzluğu sil
     */
    public function destroy(int $id): void
    {
        $nonConformity = $this->nonConformityModel->find($id);
        if (!$nonConformity) {
            Response::error('Uygunsuzluk bulunamadı', 404);
            return;
        }

        // Soft delete - status'u cancelled yap
        $oldValues = $nonConformity;
        $this->nonConformityModel->update($id, ['status' => 'cancelled']);
        
        // Audit log
        AuditLogger::logUpdate(
            '/api/v1/free-nonconformities/' . $id,
            'free_nonconformity',
            $id,
            $oldValues,
            ['status' => 'cancelled'],
            $nonConformity['created_by']
        );
        
        Response::success(null, 'Uygunsuzluk iptal edildi');
    }
}
