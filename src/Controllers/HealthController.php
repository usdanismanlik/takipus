<?php

namespace Src\Controllers;

use Src\Helpers\Response;

class HealthController
{
    public function check(): void
    {
        Response::success([
            'status' => 'ok',
            'message' => 'HSE API is running',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0.0'
        ]);
    }
}
