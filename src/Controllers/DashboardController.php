<?php

namespace Src\Controllers;

use Src\Models\Action;
use Src\Helpers\Response;

class DashboardController
{
    private Action $actionModel;

    public function __construct()
    {
        $this->actionModel = new Action();
    }

    public function stats(): void
    {
        $openActions = $this->actionModel->count(['status' => 'open']);
        $criticalActions = $this->actionModel->count(['priority' => 'critical']);

        // Basit istatistikler
        Response::success([
            'actions' => [
                'open' => $openActions,
                'critical' => $criticalActions,
                'overdue' => 0,
                'completed_this_month' => 0,
            ],
            'field_tours' => [
                'total_this_month' => 0,
                'pending' => 0,
            ],
        ]);
    }
}
