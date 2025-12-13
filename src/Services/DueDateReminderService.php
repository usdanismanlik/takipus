<?php

namespace Src\Services;

use Src\Models\Action;
use Src\Models\Notification;

class DueDateReminderService
{
    private Action $actionModel;
    private Notification $notificationModel;

    public function __construct()
    {
        $this->actionModel = new Action();
        $this->notificationModel = new Notification();
    }

    /**
     * Termin uyarılarını kontrol et ve gönder
     * Bu metod cron job ile günlük çalıştırılmalı
     */
    public function checkAndSendReminders(): array
    {
        $results = [
            'reminders_sent' => 0,
            'overdue_notifications' => 0,
            'errors' => [],
        ];

        // 1. Termin aşımı kontrolü
        $overdueActions = $this->actionModel->getOverdueActions();
        foreach ($overdueActions as $action) {
            try {
                $this->sendOverdueNotification($action);
                $this->actionModel->update($action['id'], [
                    'is_overdue' => 1,
                    'overdue_notification_sent' => 1,
                ]);
                $results['overdue_notifications']++;
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'action_id' => $action['id'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        // 2. Termin öncesi uyarılar
        $actionsNeedingReminder = $this->actionModel->getActionsNeedingReminder();
        foreach ($actionsNeedingReminder as $action) {
            try {
                if ($this->shouldSendReminder($action)) {
                    $this->sendDueDateReminder($action);
                    $this->actionModel->update($action['id'], [
                        'last_reminder_sent_at' => date('Y-m-d H:i:s'),
                    ]);
                    $results['reminders_sent']++;
                }
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'action_id' => $action['id'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Uyarı gönderilmeli mi kontrol et
     */
    private function shouldSendReminder(array $action): bool
    {
        if (!$action['due_date'] || !$action['due_date_reminder_days']) {
            return false;
        }

        $dueDate = new \DateTime($action['due_date']);
        $today = new \DateTime();
        $daysUntilDue = $today->diff($dueDate)->days;

        // Geçmiş tarih ise uyarı gönderme
        if ($dueDate < $today) {
            return false;
        }

        $reminderDays = json_decode($action['due_date_reminder_days'], true);
        if (!is_array($reminderDays)) {
            return false;
        }

        // Bugün uyarı günlerinden biri mi?
        if (!in_array($daysUntilDue, $reminderDays)) {
            return false;
        }

        // Son uyarı bugün gönderilmiş mi?
        if ($action['last_reminder_sent_at']) {
            $lastReminder = new \DateTime($action['last_reminder_sent_at']);
            $lastReminderDate = $lastReminder->format('Y-m-d');
            $todayDate = $today->format('Y-m-d');
            
            if ($lastReminderDate === $todayDate) {
                return false; // Bugün zaten gönderilmiş
            }
        }

        return true;
    }

    /**
     * Termin öncesi uyarı gönder
     */
    private function sendDueDateReminder(array $action): void
    {
        $dueDate = new \DateTime($action['due_date']);
        $today = new \DateTime();
        $daysUntilDue = $today->diff($dueDate)->days;

        $message = "'{$action['title']}' aksiyonunun termin tarihi {$daysUntilDue} gün sonra ({$action['due_date']}).";

        // Atanan kişiye bildirim
        if ($action['assigned_to_user_id']) {
            $this->notificationModel->create([
                'user_id' => $action['assigned_to_user_id'],
                'type' => 'action_due_reminder',
                'title' => "Termin Uyarısı: {$daysUntilDue} Gün Kaldı",
                'message' => $message,
                'related_type' => 'action',
                'related_id' => $action['id'],
                'notification_channel' => 'database', // Email ve push daha sonra entegre edilecek
            ]);
        }

        // Aksiyonu oluşturana da bildirim
        if ($action['created_by'] && $action['created_by'] != $action['assigned_to_user_id']) {
            $this->notificationModel->create([
                'user_id' => $action['created_by'],
                'type' => 'action_due_reminder',
                'title' => "Termin Uyarısı: {$daysUntilDue} Gün Kaldı",
                'message' => $message,
                'related_type' => 'action',
                'related_id' => $action['id'],
                'notification_channel' => 'database',
            ]);
        }
    }

    /**
     * Termin aşımı bildirimi gönder
     */
    private function sendOverdueNotification(array $action): void
    {
        $dueDate = new \DateTime($action['due_date']);
        $today = new \DateTime();
        $daysOverdue = $today->diff($dueDate)->days;

        $message = "KRİTİK: '{$action['title']}' aksiyonunun termin tarihi {$daysOverdue} gün önce geçti! Acil aksiyon gerekiyor.";

        // Atanan kişiye kritik bildirim
        if ($action['assigned_to_user_id']) {
            $this->notificationModel->create([
                'user_id' => $action['assigned_to_user_id'],
                'type' => 'action_overdue',
                'title' => "⚠️ KRİTİK: Termin Aşımı!",
                'message' => $message,
                'related_type' => 'action',
                'related_id' => $action['id'],
                'notification_channel' => 'database',
            ]);
        }

        // Aksiyonu oluşturana kritik bildirim
        if ($action['created_by'] && $action['created_by'] != $action['assigned_to_user_id']) {
            $this->notificationModel->create([
                'user_id' => $action['created_by'],
                'type' => 'action_overdue',
                'title' => "⚠️ KRİTİK: Termin Aşımı!",
                'message' => $message,
                'related_type' => 'action',
                'related_id' => $action['id'],
                'notification_channel' => 'database',
            ]);
        }
    }

    /**
     * Manuel termin kontrolü (API endpoint için)
     */
    public function checkDueDates(): array
    {
        return $this->checkAndSendReminders();
    }
}
