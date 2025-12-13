<?php

namespace Src\Helpers;

class Response
{
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function success($data, string $message = 'Success', int $status = 200): void
    {
        self::json([
            'success' => true,
            'data' => $data,
            'message' => $message,
        ], $status);
    }

    public static function error(string $message, int $status = 400, ?array $details = null): void
    {
        $response = [
            'success' => false,
            'error' => [
                'message' => $message,
            ],
        ];

        if ($details) {
            $response['error']['details'] = $details;
        }

        self::json($response, $status);
    }

    public static function paginated(array $items, int $total, int $page, int $perPage): void
    {
        self::success([
            'items' => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
            ],
        ]);
    }
}
