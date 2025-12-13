<?php

namespace Src\Controllers;

use Src\Models\Department;
use Src\Helpers\Response;

class HelperController
{
    private Department $departmentModel;

    public function __construct()
    {
        $this->departmentModel = new Department();
    }

    public function departments(): void
    {
        $departments = $this->departmentModel->all();
        Response::success($departments);
    }

    public function users(): void
    {
        // Mock data - gerçek uygulamada harici user sisteminden gelecek
        Response::success([
            [
                'id' => 1,
                'name' => 'Test User',
                'email' => 'test@hse.com',
                'department' => 'İSG Departmanı',
                'role' => 'hse',
            ],
            [
                'id' => 2,
                'name' => 'John Doe',
                'email' => 'john@hse.com',
                'department' => 'Üretim',
                'role' => 'action_owner',
            ],
        ]);
    }
}
