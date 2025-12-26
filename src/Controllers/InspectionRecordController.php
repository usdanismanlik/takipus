<?php

namespace Src\Controllers;

use Src\Models\InspectionRecord;
use Src\Models\PeriodicInspection;
use Src\Helpers\Response;
use Src\Helpers\AuditLogger;

class InspectionRecordController
{
    private InspectionRecord $recordModel;
    private PeriodicInspection $inspectionModel;

    public function __construct()
    {
        $this->recordModel = new InspectionRecord();
        $this->inspectionModel = new PeriodicInspection();
    }

    /**
     * GET /api/v1/periodic-inspections/:id/records
     * Ekipmanın kontrol geçmişini getir
     */
    public function getRecords(int $inspectionId): void
    {
        $inspection = $this->inspectionModel->find($inspectionId);
        if (!$inspection) {
            Response::error('Ekipman bulunamadı', 404);
            return;
        }

        $records = $this->recordModel->getByInspection($inspectionId);
        Response::success($records);
    }

    /**
     * POST /api/v1/periodic-inspections/:id/records
     * Yeni kontrol kaydı oluştur
     */
    public function createRecord(int $inspectionId): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $inspection = $this->inspectionModel->find($inspectionId);
        if (!$inspection) {
            Response::error('Ekipman bulunamadı', 404);
            return;
        }

        // Zorunlu alanlar
        if (!isset($data['inspection_date']) || !isset($data['inspector_user_id'])) {
            Response::error('inspection_date ve inspector_user_id zorunludur', 422);
            return;
        }

        // Bir sonraki kontrol tarihini hesapla
        $inspectionDate = $data['inspection_date'];
        $frequency = $inspection['inspection_frequency'];
        $nextInspectionDate = date('Y-m-d', strtotime($inspectionDate . ' +' . $frequency . ' days'));

        // Kontrol kaydı oluştur
        $recordId = $this->recordModel->createRecord([
            'company_id' => $inspection['company_id'],
            'inspection_id' => $inspectionId,
            'inspection_date' => $inspectionDate,
            'inspector_user_id' => $data['inspector_user_id'],
            'status' => $data['status'] ?? 'completed',
            'findings' => $data['findings'] ?? null,
            'photos' => isset($data['photos']) ? json_encode($data['photos']) : null,
            'next_inspection_date' => $nextInspectionDate,
        ]);

        $record = $this->recordModel->find($recordId);

        AuditLogger::logCreate(
            '/api/v1/periodic-inspections/' . $inspectionId . '/records',
            'inspection_record',
            $recordId,
            $record,
            $data['inspector_user_id']
        );

        Response::success($record, 'Kontrol kaydı oluşturuldu', 201);
    }

    /**
     * GET /api/v1/periodic-inspections/:id/records/latest
     * Ekipmanın son kontrol kaydını getir
     */
    public function getLatestRecord(int $inspectionId): void
    {
        $inspection = $this->inspectionModel->find($inspectionId);
        if (!$inspection) {
            Response::error('Ekipman bulunamadı', 404);
            return;
        }

        $record = $this->recordModel->getLatestByInspection($inspectionId);

        if (!$record) {
            Response::success(null, 'Kontrol kaydı bulunamadı');
            return;
        }

        Response::success($record);
    }
}
