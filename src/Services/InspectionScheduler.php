<?php

namespace Src\Services;

use Src\Models\PeriodicInspection;
use Src\Models\InspectionRecord;
use Src\Models\Action;
use Src\Services\CoreService;

class InspectionScheduler
{
    private PeriodicInspection $inspectionModel;
    private InspectionRecord $recordModel;
    private Action $actionModel;
    private CoreService $coreService;

    public function __construct()
    {
        $this->inspectionModel = new PeriodicInspection();
        $this->recordModel = new InspectionRecord();
        $this->actionModel = new Action();
        $this->coreService = new CoreService();
    }

    /**
     * Kontrolü geçmiş ekipmanları kontrol et ve aksiyon oluştur
     */
    public function checkOverdueInspections(): array
    {
        $results = [
            'checked' => 0,
            'actions_created' => 0,
            'skipped' => 0,
            'details' => []
        ];

        // Tüm aktif ekipmanları kontrol et
        $overdueInspections = $this->inspectionModel->getOverdue();
        $results['checked'] = count($overdueInspections);

        foreach ($overdueInspections as $inspection) {
            // Son 7 gün içinde kontrol yapılmış mı?
            $hasRecentInspection = $this->recordModel->hasRecentInspection(
                $inspection['id'],
                7
            );

            if ($hasRecentInspection) {
                $results['skipped']++;
                $results['details'][] = [
                    'inspection_id' => $inspection['id'],
                    'equipment_name' => $inspection['equipment_name'],
                    'status' => 'skipped',
                    'reason' => 'Son 7 gün içinde kontrol yapılmış'
                ];
                continue;
            }

            // Aksiyon oluştur
            $actionId = $this->createActionForInspection($inspection);

            if ($actionId) {
                $results['actions_created']++;
                $results['details'][] = [
                    'inspection_id' => $inspection['id'],
                    'equipment_name' => $inspection['equipment_name'],
                    'status' => 'action_created',
                    'action_id' => $actionId
                ];
            }
        }

        return $results;
    }

    /**
     * Ekipman için aksiyon oluştur
     */
    private function createActionForInspection(array $inspection): ?int
    {
        try {
            $title = "Periyodik Kontrol: " . $inspection['equipment_name'];
            $description = "Ekipman: " . $inspection['equipment_name'] . "\n";
            $description .= "Kontrol Tipi: " . $inspection['inspection_type'] . "\n";
            $description .= "Kontrol Sıklığı: " . $inspection['inspection_frequency'] . " gün\n";
            $description .= "Son Kontrol: " . ($inspection['last_inspection_date'] ?? 'Hiç yapılmamış') . "\n";
            $description .= "Planlanan Tarih: " . $inspection['next_inspection_date'];

            // Aksiyon oluştur
            $actionId = $this->actionModel->create([
                'company_id' => $inspection['company_id'],
                'title' => $title,
                'description' => $description,
                'location' => $inspection['location'] ?? 'Belirtilmemiş',
                'assigned_to' => $inspection['responsible_user_id'] ?? null,
                'due_date' => date('Y-m-d', strtotime('+7 days')), // 7 gün içinde yapılmalı
                'priority' => 'medium',
                'status' => 'open',
                'source_type' => 'periodic_inspection',
                'created_by' => 1, // System user
            ]);

            // Bildirim gönder (responsible_user_id varsa)
            if ($inspection['responsible_user_id']) {
                $this->coreService->sendPushNotification(
                    $inspection['responsible_user_id'],
                    'Periyodik Kontrol Gerekli',
                    $inspection['equipment_name'] . ' için kontrol süresi doldu',
                    'general',
                    [
                        'action_id' => $actionId,
                        'inspection_id' => $inspection['id'],
                        'type' => 'periodic_inspection'
                    ],
                    'takipus'
                );
            }

            return $actionId;
        } catch (\Exception $e) {
            error_log("Inspection action creation failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Yaklaşan kontroller için hatırlatma gönder
     */
    public function sendUpcomingReminders(int $daysAhead = 3): array
    {
        $results = [
            'checked' => 0,
            'reminders_sent' => 0,
            'details' => []
        ];

        $upcomingInspections = $this->inspectionModel->getUpcoming($daysAhead);
        $results['checked'] = count($upcomingInspections);

        foreach ($upcomingInspections as $inspection) {
            if (!$inspection['responsible_user_id']) {
                continue;
            }

            // Bildirim gönder
            $this->coreService->sendPushNotification(
                $inspection['responsible_user_id'],
                'Yaklaşan Periyodik Kontrol',
                $inspection['equipment_name'] . ' için ' . $daysAhead . ' gün içinde kontrol gerekli',
                'general',
                [
                    'inspection_id' => $inspection['id'],
                    'type' => 'upcoming_inspection'
                ],
                'takipus'
            );

            $results['reminders_sent']++;
            $results['details'][] = [
                'inspection_id' => $inspection['id'],
                'equipment_name' => $inspection['equipment_name'],
                'next_inspection_date' => $inspection['next_inspection_date']
            ];
        }

        return $results;
    }
}
