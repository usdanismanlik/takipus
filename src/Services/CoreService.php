<?php

namespace Src\Services;

class CoreService
{
    // WARNING: 'host.docker.internal' works on Docker Desktop (Mac/Windows). 
    // For Linux native docker, you might need --add-host or use network alias if on shared network.
    // 'mobileapp_php' is the container name but they are on different networks, so we use host port mapping.
    private const CORE_SERVICE_URL = 'http://host.docker.internal:8090/internal/send-notification';

    /**
     * Send a push notification via the Core Service
     *
     * @param int $userId
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array|null
     */
    public static function sendPushNotification(int $userId, string $title, string $body, array $data = [])
    {
        $payload = [
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'source_app' => 'takipus'
        ];

        $ch = curl_init(self::CORE_SERVICE_URL);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            // 'X-Internal-API-Key: ' . getenv('CORE_SERVICE_API_KEY') // Future security
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout fast if service is down

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            error_log("CoreService Curl Error: " . $error);
            return null;
        }

        if ($httpCode >= 400) {
            error_log("CoreService HTTP Error ($httpCode): " . $response);
            return null;
        }

        return json_decode($response, true);
    }
}
