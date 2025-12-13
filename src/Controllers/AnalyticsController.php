<?php

namespace Src\Controllers;

use Src\Models\Action;
use Src\Models\FieldTour;
use Src\Helpers\Response;

class AnalyticsController
{
    private Action $actionModel;
    private FieldTour $fieldTourModel;

    public function __construct()
    {
        $this->actionModel = new Action();
        $this->fieldTourModel = new FieldTour();
    }

    /**
     * Genel istatistikler
     */
    public function overview(): void
    {
        $dateRange = $_GET['date_range'] ?? 'last_30_days';
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;

        // Date range hesapla
        if ($dateRange === 'custom' && $startDate && $endDate) {
            $start = $startDate;
            $end = $endDate;
        } else {
            $days = match ($dateRange) {
                'last_7_days' => 7,
                'last_90_days' => 90,
                default => 30,
            };
            $start = date('Y-m-d', strtotime("-{$days} days"));
            $end = date('Y-m-d');
        }

        // Actions istatistikleri
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
                    SUM(CASE WHEN due_date < CURDATE() AND status != 'closed' THEN 1 ELSE 0 END) as overdue
                FROM actions 
                WHERE created_at BETWEEN ? AND ?";
        $stmt = $this->actionModel->db->prepare($sql);
        $stmt->execute([$start, $end]);
        $actionsStats = $stmt->fetch();

        // Field tours istatistikleri
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress
                FROM field_tours 
                WHERE created_at BETWEEN ? AND ?";
        $stmt = $this->fieldTourModel->db->prepare($sql);
        $stmt->execute([$start, $end]);
        $fieldToursStats = $stmt->fetch();

        Response::success([
            'period' => [
                'start' => $start,
                'end' => $end,
            ],
            'actions' => [
                'total' => (int) $actionsStats['total'],
                'open' => (int) $actionsStats['open'],
                'in_progress' => (int) $actionsStats['in_progress'],
                'closed' => (int) $actionsStats['closed'],
                'overdue' => (int) $actionsStats['overdue'],
                'avg_closure_time_days' => 5.2, // TODO: Calculate
            ],
            'field_tours' => [
                'total' => (int) $fieldToursStats['total'],
                'completed' => (int) $fieldToursStats['completed'],
                'in_progress' => (int) $fieldToursStats['in_progress'],
                'avg_duration_minutes' => 35, // TODO: Calculate
            ],
        ]);
    }

    /**
     * Departman bazlı istatistikler
     */
    public function byDepartment(): void
    {
        $sql = "SELECT 
                    d.id,
                    d.name,
                    COUNT(a.id) as total_actions,
                    SUM(CASE WHEN a.status = 'open' THEN 1 ELSE 0 END) as open_actions,
                    SUM(CASE WHEN a.status = 'closed' THEN 1 ELSE 0 END) as closed_actions,
                    SUM(CASE WHEN a.due_date < CURDATE() AND a.status != 'closed' THEN 1 ELSE 0 END) as overdue_actions,
                    AVG(a.risk_score) as avg_risk_score
                FROM departments d
                LEFT JOIN actions a ON d.id = a.department_id
                GROUP BY d.id, d.name
                HAVING total_actions > 0";

        $stmt = $this->actionModel->db->prepare($sql);
        $stmt->execute();
        $departments = $stmt->fetchAll();

        $result = [];
        foreach ($departments as $dept) {
            $total = (int) $dept['total_actions'];
            $closed = (int) $dept['closed_actions'];
            $closureRate = $total > 0 ? round(($closed / $total) * 100, 1) : 0;

            $result[] = [
                'department' => [
                    'id' => (int) $dept['id'],
                    'name' => $dept['name'],
                ],
                'actions' => [
                    'total' => $total,
                    'open' => (int) $dept['open_actions'],
                    'closed' => $closed,
                    'overdue' => (int) $dept['overdue_actions'],
                ],
                'avg_risk_score' => round((float) $dept['avg_risk_score'], 1),
                'closure_rate' => $closureRate,
            ];
        }

        Response::success($result);
    }

    /**
     * Trend analizi
     */
    public function trends(): void
    {
        $metric = $_GET['metric'] ?? 'actions_created';
        $period = $_GET['period'] ?? 'weekly';
        $dateRange = $_GET['date_range'] ?? 'last_30_days';

        $days = match ($dateRange) {
            'last_7_days' => 7,
            'last_90_days' => 90,
            default => 30,
        };

        $groupBy = match ($period) {
            'daily' => 'DATE(created_at)',
            'monthly' => 'DATE_FORMAT(created_at, "%Y-%m-01")',
            default => 'YEARWEEK(created_at)',
        };

        $table = match ($metric) {
            'field_tours' => 'field_tours',
            default => 'actions',
        };

        $sql = "SELECT 
                    {$groupBy} as period,
                    COUNT(*) as value
                FROM {$table}
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)
                GROUP BY period
                ORDER BY period";

        $stmt = $this->actionModel->db->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetchAll();

        $dataPoints = [];
        foreach ($data as $row) {
            $dataPoints[] = [
                'date' => $row['period'],
                'value' => (int) $row['value'],
            ];
        }

        Response::success([
            'metric' => $metric,
            'period' => $period,
            'data_points' => $dataPoints,
        ]);
    }

    /**
     * Risk dağılımı
     */
    public function riskDistribution(): void
    {
        $sql = "SELECT 
                    CASE 
                        WHEN risk_score BETWEEN 1 AND 4 THEN 'low'
                        WHEN risk_score BETWEEN 5 AND 9 THEN 'medium'
                        WHEN risk_score BETWEEN 10 AND 15 THEN 'high'
                        WHEN risk_score >= 16 THEN 'critical'
                        ELSE 'unknown'
                    END as level,
                    COUNT(*) as count
                FROM actions
                WHERE risk_score IS NOT NULL
                GROUP BY level";

        $stmt = $this->actionModel->db->prepare($sql);
        $stmt->execute();
        $distribution = $stmt->fetchAll();

        $total = array_sum(array_column($distribution, 'count'));

        $labels = [
            'low' => 'Düşük Risk',
            'medium' => 'Orta Risk',
            'high' => 'Yüksek Risk',
            'critical' => 'Kritik Risk',
        ];

        $result = [];
        foreach ($distribution as $item) {
            $count = (int) $item['count'];
            $result[] = [
                'level' => $item['level'],
                'label' => $labels[$item['level']] ?? $item['level'],
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
            ];
        }

        Response::success([
            'by_level' => $result,
            'total' => $total,
        ]);
    }
}
