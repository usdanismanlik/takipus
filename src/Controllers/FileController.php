<?php

namespace Src\Controllers;

use Src\Helpers\Response;

class FileController
{
    private string $endpoint = 'files-api.apps.misafirus.com';
    private string $accessKey = '8d2b5f417f60ef4456765766';
    private string $secretKey = 'aabf96bc25a790c3ec944155ab6348fd0840e3';
    private string $bucket = 'takipus';

    /**
     * Upload a file to S3-compatible storage
     */
    public function upload(): void
    {
        if (!isset($_FILES['file'])) {
            Response::error('No file uploaded', 422);
            return;
        }

        $file = $_FILES['file'];

        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Response::error('File upload error', 500);
            return;
        }

        // Validate file type (images only)
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            Response::error('Invalid file type. Only images are allowed.', 422);
            return;
        }

        // Validate file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            Response::error('File size exceeds 5MB limit', 422);
            return;
        }

        try {
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'hse/' . date('Y/m/d') . '/' . uniqid('img_', true) . '.' . $extension;

            // Read file content
            $fileContent = file_get_contents($file['tmp_name']);

            // Prepare S3 request
            $url = "https://{$this->endpoint}/{$this->bucket}/{$filename}";
            $date = gmdate('D, d M Y H:i:s T');
            $contentType = $mimeType;

            // Create signature
            $stringToSign = "PUT\n\n{$contentType}\n{$date}\n/{$this->bucket}/{$filename}";
            $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->secretKey, true));

            // Upload to S3
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Date: {$date}",
                "Content-Type: {$contentType}",
                "Content-Length: " . strlen($fileContent),
                "Authorization: AWS {$this->accessKey}:{$signature}",
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                error_log("cURL error: {$curlError}");
                Response::error('Network error: ' . $curlError, 500);
                return;
            }

            if ($httpCode !== 200) {
                error_log("S3 upload failed: HTTP {$httpCode}, Response: {$response}");
                Response::error("Failed to upload file to storage (HTTP {$httpCode})", 500);
                return;
            }

            // Return file URL
            $fileUrl = "https://{$this->endpoint}/{$this->bucket}/{$filename}";

            Response::success([
                'url' => $fileUrl,
                'filename' => $filename,
                'size' => $file['size'],
                'mime_type' => $mimeType,
            ], 'File uploaded successfully', 201);

        } catch (\Exception $e) {
            error_log("Upload error: " . $e->getMessage());
            Response::error('Failed to upload file: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a file from S3-compatible storage
     */
    public function delete(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['filename'])) {
            Response::error('Filename is required', 422);
            return;
        }

        try {
            $filename = $data['filename'];

            // Prepare S3 delete request
            $url = "https://{$this->endpoint}/{$this->bucket}/{$filename}";
            $date = gmdate('D, d M Y H:i:s T');

            // Create signature
            $stringToSign = "DELETE\n\n\n{$date}\n/{$this->bucket}/{$filename}";
            $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->secretKey, true));

            // Delete from S3
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Date: {$date}",
                "Authorization: AWS {$this->accessKey}:{$signature}",
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 204 && $httpCode !== 200) {
                error_log("S3 delete failed: HTTP {$httpCode}, Response: {$response}");
                Response::error('Failed to delete file from storage', 500);
                return;
            }

            Response::success(null, 'File deleted successfully');

        } catch (\Exception $e) {
            error_log("Delete error: " . $e->getMessage());
            Response::error('Failed to delete file: ' . $e->getMessage(), 500);
        }
    }
}
