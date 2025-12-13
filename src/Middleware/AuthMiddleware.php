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

            // Token'dan kullanıcı bilgilerini al
            $GLOBALS['auth_user_id'] = $payload->user_id;
            $GLOBALS['auth_user_role'] = $payload->role ?? 'user';
            $GLOBALS['auth_user_permissions'] = $payload->permissions ?? [];
            $GLOBALS['auth_company_id'] = $payload->company_id ?? null;
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
        $GLOBALS['auth_user_role'] = $_SESSION['user_role'] ?? 'user';
        $GLOBALS['auth_user_permissions'] = $_SESSION['user_permissions'] ?? [];
        $GLOBALS['auth_company_id'] = $_SESSION['company_id'] ?? null;
        return true;
    }

    public static function getUserId(): ?int
    {
        return $GLOBALS['auth_user_id'] ?? null;
    }

    public static function getUserRole(): ?string
    {
        return $GLOBALS['auth_user_role'] ?? null;
    }

    public static function getUserPermissions(): array
    {
        return $GLOBALS['auth_user_permissions'] ?? [];
    }

    public static function getCompanyId(): ?string
    {
        return $GLOBALS['auth_company_id'] ?? null;
    }

    public static function getAuthUser(): array
    {
        return [
            'user_id' => self::getUserId(),
            'role' => self::getUserRole(),
            'permissions' => self::getUserPermissions(),
            'company_id' => self::getCompanyId(),
        ];
    }
}
