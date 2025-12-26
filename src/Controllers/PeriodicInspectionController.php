<?php

namespace Src\Controllers;

use Src\Models\PeriodicInspection;
use Src\Models\Notification;
use Src\Helpers\Response;
use Src\Helpers\AuditLogger;

class PeriodicInspectionController
{
    private PeriodicInspection $inspectionModel;
    private Notification $notificationModel;

    public function __construct()
    {
        $this->inspectionModel = new PeriodicInspection();
        $this->notificationModel = new Notification();
    }

    /**
     * POST /api/v1/periodic-inspections
     * Periyodik kontrol oluştur
     */
    public function store(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['company_id']) || !isset($data['equipment_name']) || !isset($data['inspection_frequency'])) {
            Response::error('company_id, equipment_name ve inspection_frequency zorunludur', 422);
            return;
        }

        // İlk kontrol tarihi
        $nextInspectionDate = $data['next_inspection_date'] ?? date('Y-m-d', strtotime('+' . $data['inspection_frequency'] . ' days'));

        $inspectionId = $this->inspectionModel->create([
            'company_id' => $data['company_id'],
            'equipment_name' => $data['equipment_name'],
            'equipment_code' => $data['equipment_code'] ?? null,
            'inspection_type' => $data['inspection_type'] ?? 'Genel Kontrol',
            'inspection_frequency' => $data['inspection_frequency'],
            'next_inspection_date' => $nextInspectionDate,
            'responsible_user_id' => $data['responsible_user_id'] ?? null,
            'location' => $data['location'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);

        $inspection = $this->inspectionModel->find($inspectionId);

        AuditLogger::logCreate(
            '/api/v1/periodic-inspections',
            'periodic_inspection',
            $inspectionId,
            $inspection,
            $data['created_by'] ?? null
        );

        Response::success($inspection, 'Periyodik kontrol oluşturuldu', 201);
    }

    /**
     * GET /api/v1/periodic-inspections
     * Periyodik kontrolleri listele
     */
    public function index(): void
    {
        $companyId = $_GET['company_id'] ?? null;
        $status = $_GET['status'] ?? null;

        if (!$companyId) {
            Response::error('company_id parametresi zorunludur', 422);
            return;
        }

        $inspections = $this->inspectionModel->getByCompany($companyId, $status);
        Response::success($inspections);
    }

    /**
     * GET /api/v1/periodic-inspections/upcoming
     * Yaklaşan kontroller
     */
    public function getUpcoming(): void
    {
        $daysAhead = $_GET['days_ahead'] ?? 7;
        $inspections = $this->inspectionModel->getUpcoming((int) $daysAhead);
        Response::success($inspections);
    }

    /**
     * GET /api/v1/periodic-inspections/overdue
     * Gecikmiş kontroller
     */
    public function getOverdue(): void
    {
        $inspections = $this->inspectionModel->getOverdue();
        Response::success($inspections);
    }

    /**
     * GET /api/v1/periodic-inspections/:id
     * Belirli bir periyodik kontrolü getir
     */
    public function show(int $id): void
    {
        $inspection = $this->inspectionModel->find($id);
        if (!$inspection) {
            Response::error('Periyodik kontrol bulunamadı', 404);
            return;
        }
        Response::success($inspection);
    }

    /**
     * POST /api/v1/periodic-inspections/:id/complete
     * Kontrolü tamamla
     */
    public function complete(int $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $inspection = $this->inspectionModel->find($id);
        if (!$inspection) {
            Response::error('Periyodik kontrol bulunamadı', 404);
            return;
        }

        // Bir sonraki kontrol tarihini hesapla
        $nextDate = date('Y-m-d', strtotime('+' . $inspection['inspection_frequency'] . ' days'));

        $this->inspectionModel->update($id, [
            'last_inspection_date' => date('Y-m-d'),
            'next_inspection_date' => $nextDate,
            'notes' => $data['notes'] ?? $inspection['notes'],
        ]);

        // Eğer kontrol sonucu uygunsuzluk varsa, bu bilgi döndürülür
        // Frontend'de manuel aksiyon oluşturma ekranına yönlendirilir

        $updated = $this->inspectionModel->find($id);
        Response::success($updated, 'Kontrol tamamlandı');
    }

    /**
     * PUT /api/v1/periodic-inspections/:id
     * Periyodik kontrolü güncelle
     */
    public function update(int $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $inspection = $this->inspectionModel->find($id);
        if (!$inspection) {
            Response::error('Periyodik kontrol bulunamadı', 404);
            return;
        }

        $updateData = [];
        if (isset($data['equipment_name']))
            $updateData['equipment_name'] = $data['equipment_name'];
        if (isset($data['equipment_code']))
            $updateData['equipment_code'] = $data['equipment_code'];
        if (isset($data['inspection_type']))
            $updateData['inspection_type'] = $data['inspection_type'];
        if (isset($data['inspection_frequency']))
            $updateData['inspection_frequency'] = $data['inspection_frequency'];
        if (isset($data['next_inspection_date']))
            $updateData['next_inspection_date'] = $data['next_inspection_date'];
        if (isset($data['responsible_user_id']))
            $updateData['responsible_user_id'] = $data['responsible_user_id'];
        if (isset($data['location']))
            $updateData['location'] = $data['location'];
        if (isset($data['status']))
            $updateData['status'] = $data['status'];
        if (isset($data['notes']))
            $updateData['notes'] = $data['notes'];

        if (!empty($updateData)) {
            $this->inspectionModel->update($id, $updateData);

            AuditLogger::logUpdate(
                '/api/v1/periodic-inspections/' . $id,
                'periodic_inspection',
                $id,
                $inspection,
                $this->inspectionModel->find($id),
                $inspection['created_by']
            );
        }

        $updated = $this->inspectionModel->find($id);
        Response::success($updated, 'Periyodik kontrol güncellendi');
    }
}
