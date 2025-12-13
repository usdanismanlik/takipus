<?php

namespace Src\Controllers;

use Src\Models\Action;
use Src\Helpers\Response;
use Src\Helpers\RiskMatrix;
use Src\Config\Database;

class DashboardController
{
    private Action $actionModel;

    public function __construct()
    {
        $this->actionModel = new Action();
    }
    
    private function getDb(): \PDO
    {
        return Database::getConnection();
    }

    /**
     * GET /api/v1/dashboard/statistics
     * Dashboard istatistikleri
     */
    public function getStatistics(): void
    {
        $companyId = $_GET['company_id'] ?? null;

        if (!$companyId) {
            Response::error('company_id parametresi zorunludur', 422);
            return;
        }

        $stats = [
            'total_actions' => $this->getTotalActions($companyId),
            'open_actions' => $this->getOpenActions($companyId),
            'critical_actions' => $this->getCriticalActions($companyId),
            'overdue_actions' => $this->getOverdueActions($companyId),
            'completed_this_month' => $this->getCompletedThisMonth($companyId),
            'by_status' => $this->getActionsByStatus($companyId),
            'by_risk_level' => $this->getActionsByRiskLevel($companyId),
            'by_department' => $this->getActionsByDepartment($companyId),
            'by_source' => $this->getActionsBySource($companyId),
            'by_priority' => $this->getActionsByPriority($companyId),
        ];

        Response::success($stats);
    }

    /**
     * GET /api/v1/dashboard/risk-matrix
     * Risk matrisi tablosu
     */
    public function getRiskMatrix(): void
    {
        $matrix = RiskMatrix::getMatrix();
        $descriptions = RiskMatrix::getRiskLevelDescriptions();
        $probabilityLevels = RiskMatrix::getProbabilityLevels();
        $severityLevels = RiskMatrix::getSeverityLevels();

        Response::success([
            'matrix' => $matrix,
            'descriptions' => $descriptions,
            'probability_levels' => $probabilityLevels,
            'severity_levels' => $severityLevels,
        ]);
    }

    /**
     * GET /api/v1/dashboard/actions/prioritized
     * Risk bazlı önceliklendirilmiş aksiyonlar
     */
    public function getPrioritizedActions(): void
    {
        $companyId = $_GET['company_id'] ?? null;
        $status = $_GET['status'] ?? 'open,in_progress,pending_approval';

        if (!$companyId) {
            Response::error('company_id parametresi zorunludur', 422);
            return;
        }

        $statusArray = explode(',', $status);
        $placeholders = str_repeat('?,', count($statusArray) - 1) . '?';

        $sql = "SELECT a.*, 
                (a.risk_probability * a.risk_severity) as calculated_risk_score
                FROM actions a
                WHERE a.company_id = ? 
                AND a.status IN ($placeholders)
                ORDER BY calculated_risk_score DESC, a.due_date ASC
                LIMIT 50";

        $params = array_merge([$companyId], $statusArray);
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($params);
        $actions = $stmt->fetchAll();

        // Risk bilgilerini ekle
        foreach ($actions as &$action) {
            if ($action['risk_probability'] && $action['risk_severity']) {
                $risk = RiskMatrix::calculateRisk(
                    $action['risk_probability'],
                    $action['risk_severity']
                );
                $action['risk_info'] = $risk;
            }
        }

        Response::success($actions);
    }

    /**
     * GET /api/v1/dashboard/actions/real-time
     * Canlı aksiyon tablosu
     */
    public function getRealTimeActions(): void
    {
        $companyId = $_GET['company_id'] ?? null;

        if (!$companyId) {
            Response::error('company_id parametresi zorunludur', 422);
            return;
        }

        $sql = "SELECT a.*,
                (a.risk_probability * a.risk_severity) as calculated_risk_score,
                DATEDIFF(a.due_date, CURDATE()) as days_until_due
                FROM actions a
                WHERE a.company_id = ?
                AND a.status != 'completed'
                AND a.status != 'cancelled'
                ORDER BY calculated_risk_score DESC, a.due_date ASC";

        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute([$companyId]);
        $actions = $stmt->fetchAll();

        // Risk bilgilerini ve ek metadata ekle
        foreach ($actions as &$action) {
            if ($action['risk_probability'] && $action['risk_severity']) {
                $risk = RiskMatrix::calculateRisk(
                    $action['risk_probability'],
                    $action['risk_severity']
                );
                $action['risk_info'] = $risk;
            }

            // Termin durumu
            if ($action['due_date']) {
                $action['due_status'] = $this->getDueStatus($action['days_until_due']);
            }

            // JSON alanları decode et
            if ($action['due_date_reminder_days']) {
                $action['due_date_reminder_days'] = json_decode($action['due_date_reminder_days'], true);
            }
        }

        Response::success($actions);
    }

    // Private helper metodlar

    private function getTotalActions(string $companyId): int
    {
        $sql = "SELECT COUNT(*) as count FROM actions WHERE company_id = ?";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute([$companyId]);
        return (int)$stmt->fetch()['count'];
    }

    private function getOpenActions(string $companyId): int
    {
        $sql = "SELECT COUNT(*) as count FROM actions 
                WHERE company_id = ? 
                AND status IN ('open', 'in_progress', 'pending_approval')";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute([$companyId]);
        return (int)$stmt->fetch()['count'];
    }

    private function getCriticalActions(string $companyId): int
    {
        $sql = "SELECT COUNT(*) as count FROM actions 
                WHERE company_id = ? 
                AND status IN ('open', 'in_progress')
                AND risk_level IN ('high', 'very_high')";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute([$companyId]);
        return (int)$stmt->fetch()['count'];
    }

    private function getOverdueActions(string $companyId): int
    {
        $sql = "SELECT COUNT(*) as count FROM actions 
                WHERE company_id = ? 
                AND status IN ('open', 'in_progress', 'pending_approval')
                AND due_date < CURDATE()";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute([$companyId]);
        return (int)$stmt->fetch()['count'];
    }

    private function getCompletedThisMonth(string $companyId): int
    {
        $sql = "SELECT COUNT(*) as count FROM actions 
                WHERE company_id = ? 
                AND status = 'completed'
                AND MONTH(completed_at) = MONTH(CURDATE())
                AND YEAR(completed_at) = YEAR(CURDATE())";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute([$companyId]);
        return (int)$stmt->fetch()['count'];
    }

    private function getActionsByStatus(string $companyId): array
    {
        $sql = "SELECT status, COUNT(*) as count 
                FROM actions 
                WHERE company_id = ? 
                GROUP BY status";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute([$companyId]);
        return $stmt->fetchAll();
    }

    private function getActionsByRiskLevel(string $companyId): array
    {
        $sql = "SELECT risk_level, COUNT(*) as count 
                FROM actions 
                WHERE company_id = ? 
                AND status IN ('open', 'in_progress', 'pending_approval')
                AND risk_level IS NOT NULL
                GROUP BY risk_level";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute([$companyId]);
        return $stmt->fetchAll();
    }

    private function getActionsByDepartment(string $companyId): array
    {
        $sql = "SELECT assigned_to_department_id, COUNT(*) as count 
                FROM actions 
                WHERE company_id = ? 
                AND status IN ('open', 'in_progress', 'pending_approval')
                AND assigned_to_department_id IS NOT NULL
                GROUP BY assigned_to_department_id";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute([$companyId]);
        return $stmt->fetchAll();
    }

    private function getActionsBySource(string $companyId): array
    {
        $sql = "SELECT source_type, COUNT(*) as count 
                FROM actions 
                WHERE company_id = ? 
                AND status IN ('open', 'in_progress', 'pending_approval')
                GROUP BY source_type";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute([$companyId]);
        return $stmt->fetchAll();
    }

    private function getActionsByPriority(string $companyId): array
    {
        $sql = "SELECT priority, COUNT(*) as count 
                FROM actions 
                WHERE company_id = ? 
                AND status IN ('open', 'in_progress', 'pending_approval')
                GROUP BY priority";
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute([$companyId]);
        return $stmt->fetchAll();
    }

    private function getDueStatus(int $daysUntilDue): string
    {
        if ($daysUntilDue < 0) return 'overdue';
        if ($daysUntilDue == 0) return 'due_today';
        if ($daysUntilDue <= 3) return 'due_soon';
        if ($daysUntilDue <= 7) return 'due_this_week';
        return 'on_track';
    }
}
