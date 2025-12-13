<?php

namespace Src\Controllers;

use Src\Models\Action;
use Src\Helpers\Response;

class ExportController
{
    private Action $actionModel;

    public function __construct()
    {
        $this->actionModel = new Action();
    }

    /**
     * GET /api/v1/export/actions/excel
     * Aksiyonları Excel formatında dışa aktar
     */
    public function exportActionsExcel(): void
    {
        $companyId = $_GET['company_id'] ?? null;
        $status = $_GET['status'] ?? null;

        if (!$companyId) {
            Response::error('company_id parametresi zorunludur', 422);
            return;
        }

        $actions = $this->getActionsForExport($companyId, $status);

        // CSV formatında döndür (Excel ile açılabilir)
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="aksiyonlar_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        
        // UTF-8 BOM ekle (Türkçe karakterler için)
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Başlıklar
        fputcsv($output, [
            'ID',
            'Başlık',
            'Açıklama',
            'Durum',
            'Öncelik',
            'Risk Seviyesi',
            'Risk Puanı',
            'Kaynak',
            'Lokasyon',
            'Atanan Kişi ID',
            'Atanan Departman ID',
            'Termin Tarihi',
            'Oluşturulma Tarihi',
            'Tamamlanma Tarihi',
        ], ';');

        // Veriler
        foreach ($actions as $action) {
            fputcsv($output, [
                $action['id'],
                $action['title'],
                $action['description'],
                $action['status'],
                $action['priority'],
                $action['risk_level'] ?? '',
                $action['risk_score'] ?? '',
                $action['source_type'] ?? '',
                $action['location'] ?? '',
                $action['assigned_to_user_id'] ?? '',
                $action['assigned_to_department_id'] ?? '',
                $action['due_date'] ?? '',
                $action['created_at'],
                $action['completed_at'] ?? '',
            ], ';');
        }

        fclose($output);
        exit;
    }

    /**
     * GET /api/v1/export/actions/csv
     * Aksiyonları CSV formatında dışa aktar
     */
    public function exportActionsCsv(): void
    {
        // Excel ile aynı, sadece header farklı
        $this->exportActionsExcel();
    }

    /**
     * GET /api/v1/export/actions/json
     * Aksiyonları JSON formatında dışa aktar
     */
    public function exportActionsJson(): void
    {
        $companyId = $_GET['company_id'] ?? null;
        $status = $_GET['status'] ?? null;

        if (!$companyId) {
            Response::error('company_id parametresi zorunludur', 422);
            return;
        }

        $actions = $this->getActionsForExport($companyId, $status);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="aksiyonlar_' . date('Y-m-d') . '.json"');

        echo json_encode([
            'export_date' => date('Y-m-d H:i:s'),
            'company_id' => $companyId,
            'total_actions' => count($actions),
            'actions' => $actions,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        exit;
    }

    /**
     * Export için aksiyonları getir
     */
    private function getActionsForExport(string $companyId, ?string $status): array
    {
        $sql = "SELECT a.*,
                (a.risk_probability * a.risk_severity) as calculated_risk_score
                FROM actions a
                WHERE a.company_id = ?";
        
        $params = [$companyId];

        if ($status) {
            $statusArray = explode(',', $status);
            $placeholders = str_repeat('?,', count($statusArray) - 1) . '?';
            $sql .= " AND a.status IN ($placeholders)";
            $params = array_merge($params, $statusArray);
        }

        $sql .= " ORDER BY a.created_at DESC";

        $stmt = $this->actionModel->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
