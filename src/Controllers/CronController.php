<?php

namespace Src\Controllers;

use Src\Services\InspectionScheduler;
use Src\Helpers\Response;

class CronController
{
    private InspectionScheduler $scheduler;

    public function __construct()
    {
        $this->scheduler = new InspectionScheduler();
    }

    /**
     * POST /api/v1/cron/check-inspections
     * Kontrolü geçmiş ekipmanları kontrol et ve aksiyon oluştur
     */
    public function checkInspections(): void
    {
        try {
            $results = $this->scheduler->checkOverdueInspections();
            Response::success($results, 'Kontrol tamamlandı');
        } catch (\Exception $e) {
            error_log("Cron check inspections error: " . $e->getMessage());
            Response::error('Kontrol sırasında hata oluştu: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/cron/send-reminders
     * Yaklaşan kontroller için hatırlatma gönder
     */
    public function sendReminders(): void
    {
        try {
            $daysAhead = $_GET['days_ahead'] ?? 3;
            $results = $this->scheduler->sendUpcomingReminders((int) $daysAhead);
            Response::success($results, 'Hatırlatmalar gönderildi');
        } catch (\Exception $e) {
            error_log("Cron send reminders error: " . $e->getMessage());
            Response::error('Hatırlatma gönderimi sırasında hata oluştu: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/cron/status
     * Cron durumu ve istatistikler
     */
    public function status(): void
    {
        try {
            $inspectionModel = new \Src\Models\PeriodicInspection();

            $overdueCount = count($inspectionModel->getOverdue());
            $upcomingCount = count($inspectionModel->getUpcoming(7));

            Response::success([
                'overdue_inspections' => $overdueCount,
                'upcoming_inspections_7days' => $upcomingCount,
                'last_check' => date('Y-m-d H:i:s'),
            ], 'Cron durumu');
        } catch (\Exception $e) {
            error_log("Cron status error: " . $e->getMessage());
            Response::error('Durum kontrolü sırasında hata oluştu: ' . $e->getMessage(), 500);
        }
    }
}
