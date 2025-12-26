<?php

namespace Src\Controllers;

use Src\Services\InspectionScheduler;
use Src\Services\DueDateReminderService;
use Src\Helpers\Response;

class CronController
{
    private InspectionScheduler $scheduler;
    private DueDateReminderService $reminderService;

    public function __construct()
    {
        $this->scheduler = new InspectionScheduler();
        $this->reminderService = new DueDateReminderService();
    }

    /**
     * POST /api/v1/cron/daily-check
     * Günlük kontrol: Periyodik kontroller + Aksiyon hatırlatıcıları
     * Her sabah 09:00'da çalıştırılmalı
     */
    public function dailyCheck(): void
    {
        try {
            $results = [
                'timestamp' => date('Y-m-d H:i:s'),
                'periodic_inspections' => null,
                'action_reminders' => null,
            ];

            // 1. Periyodik kontrolleri kontrol et
            $results['periodic_inspections'] = $this->scheduler->checkOverdueInspections();

            // 2. Aksiyon hatırlatıcılarını gönder
            $results['action_reminders'] = $this->reminderService->checkAndSendReminders();

            Response::success($results, 'Günlük kontrol tamamlandı');
        } catch (\Exception $e) {
            error_log("Daily check error: " . $e->getMessage());
            Response::error('Günlük kontrol sırasında hata oluştu: ' . $e->getMessage(), 500);
        }
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
            $results = $this->scheduler->sendUpcomingReminders((int)$daysAhead);
            Response::success($results, 'Hatırlatmalar gönderildi');
        } catch (\Exception $e) {
            error_log("Cron send reminders error: " . $e->getMessage());
            Response::error('Hatırlatma gönderimi sırasında hata oluştu: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/cron/check-action-due-dates
     * Aksiyon termin tarihlerini kontrol et ve hatırlatma gönder
     */
    public function checkActionDueDates(): void
    {
        try {
            $results = $this->reminderService->checkAndSendReminders();
            Response::success($results, 'Aksiyon hatırlatıcıları gönderildi');
        } catch (\Exception $e) {
            error_log("Cron check action due dates error: " . $e->getMessage());
            Response::error('Aksiyon hatırlatıcı gönderimi sırasında hata oluştu: ' . $e->getMessage(), 500);
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
            $actionModel = new \Src\Models\Action();
            
            $overdueInspections = count($inspectionModel->getOverdue());
            $upcomingInspections = count($inspectionModel->getUpcoming(7));
            $overdueActions = count($actionModel->getOverdueActions());
            $actionsNeedingReminder = count($actionModel->getActionsNeedingReminder());
            
            Response::success([
                'overdue_inspections' => $overdueInspections,
                'upcoming_inspections_7days' => $upcomingInspections,
                'overdue_actions' => $overdueActions,
                'actions_needing_reminder' => $actionsNeedingReminder,
                'last_check' => date('Y-m-d H:i:s'),
            ], 'Cron durumu');
        } catch (\Exception $e) {
            error_log("Cron status error: " . $e->getMessage());
            Response::error('Durum kontrolü sırasında hata oluştu: ' . $e->getMessage(), 500);
        }
    }
}