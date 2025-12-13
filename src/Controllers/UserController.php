<?php

namespace Src\Controllers;

use Src\Helpers\Response;

class UserController
{
    /**
     * Mock user endpoint - simulates external user API
     * In production, this will call the main app's user API
     */
    public function getUser(int $id): void
    {
        // Mock user data - simulates external API response
        $mockUsers = [
            1 => [
                'id' => 1,
                'name' => 'Test User',
                'email' => 'test@hse.com',
                'role' => 'hse',
                'department' => 'İSG Departmanı'
            ],
            2 => [
                'id' => 2,
                'name' => 'Admin User',
                'email' => 'admin@hse.com',
                'role' => 'admin',
                'department' => 'Yönetim'
            ],
            3 => [
                'id' => 3,
                'name' => 'Field Inspector',
                'email' => 'inspector@hse.com',
                'role' => 'inspector',
                'department' => 'Saha Ekibi'
            ],
        ];

        if (!isset($mockUsers[$id])) {
            Response::error('User not found', 404);
            return;
        }

        Response::success($mockUsers[$id]);
    }

    /**
     * Get multiple users by IDs
     */
    public function getUsersByIds(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $ids = $data['ids'] ?? [];

        if (empty($ids)) {
            Response::error('User IDs required', 422);
            return;
        }

        $mockUsers = [
            1 => ['id' => 1, 'name' => 'Test User', 'email' => 'test@hse.com'],
            2 => ['id' => 2, 'name' => 'Admin User', 'email' => 'admin@hse.com'],
            3 => ['id' => 3, 'name' => 'Field Inspector', 'email' => 'inspector@hse.com'],
        ];

        $users = [];
        foreach ($ids as $id) {
            if (isset($mockUsers[$id])) {
                $users[] = $mockUsers[$id];
            }
        }

        Response::success($users);
    }

    /**
     * Search users by name or email
     */
    public function searchUsers(): void
    {
        $query = $_GET['q'] ?? '';

        if (strlen($query) < 2) {
            Response::error('Query must be at least 2 characters', 422);
            return;
        }

        // Mock user list
        $allUsers = [
            ['id' => 1, 'name' => 'Test User', 'email' => 'test@hse.com', 'role' => 'hse'],
            ['id' => 2, 'name' => 'Admin User', 'email' => 'admin@hse.com', 'role' => 'admin'],
            ['id' => 3, 'name' => 'Field Inspector', 'email' => 'inspector@hse.com', 'role' => 'inspector'],
            ['id' => 4, 'name' => 'Safety Manager', 'email' => 'safety@hse.com', 'role' => 'manager'],
            ['id' => 5, 'name' => 'HSE Coordinator', 'email' => 'coordinator@hse.com', 'role' => 'coordinator'],
        ];

        // Filter by query
        $query = strtolower($query);
        $results = array_filter($allUsers, function ($user) use ($query) {
            return stripos($user['name'], $query) !== false ||
                stripos($user['email'], $query) !== false;
        });

        Response::success(array_values($results));
    }
}
