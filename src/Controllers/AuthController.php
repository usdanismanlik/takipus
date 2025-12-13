<?php

namespace Src\Controllers;

use Src\Helpers\JWT;
use Src\Helpers\Response;

class AuthController
{
    public function login(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['email']) || !isset($data['password'])) {
            Response::error('Email and password are required', 422);
            return;
        }

        // Basit test için - gerçek uygulamada harici user sisteminden kontrol edilecek
        if ($data['email'] === 'test@hse.com' && $data['password'] === 'test123') {
            $token = JWT::encode([
                'user_id' => 1,
                'email' => $data['email'],
                'name' => 'Test User',
            ]);

            Response::success([
                'user' => [
                    'id' => 1,
                    'name' => 'Test User',
                    'email' => $data['email'],
                    'role' => 'hse',
                    'department' => 'İSG Departmanı',
                ],
                'tokens' => [
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                ],
            ], 'Login successful');
        } else {
            Response::error('Invalid credentials', 401);
        }
    }

    public function me(): void
    {
        // Test user data
        Response::success([
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@hse.com',
            'role' => 'hse',
            'department' => 'İSG Departmanı',
        ]);
    }

    public function logout(): void
    {
        Response::success(null, 'Logged out successfully');
    }
}
