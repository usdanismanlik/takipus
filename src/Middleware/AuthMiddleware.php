<?php

namespace Src\Middleware;

use Src\Helpers\JWT;
use Src\Helpers\Response;

class AuthMiddleware
{
    public static function handle(): bool
    {
        // Mobil için JWT kontrolü
        $token = JWT::getTokenFromHeader();

        if ($token) {
            $payload = JWT::decode($token);

            if (!$payload) {
                Response::error('Invalid or expired token', 401);
                return false;
            }

            $GLOBALS['auth_user_id'] = $payload->user_id;
            return true;
        }

        // Web için session kontrolü
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            Response::error('Unauthorized', 401);
            return false;
        }

        $GLOBALS['auth_user_id'] = $_SESSION['user_id'];
        return true;
    }

    public static function getUserId(): ?int
    {
        return $GLOBALS['auth_user_id'] ?? null;
    }
}
