<?php

namespace Src\Controllers;

use Src\Helpers\Response;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class FileUploadController
{
    private S3Client $s3Client;
    private string $bucket;
    private string $region;

    public function __construct()
    {
        $this->bucket = $_ENV['S3_BUCKET'] ?? 'takipus';
        $this->region = $_ENV['S3_REGION'] ?? 'us-east-1';
        
        $endpoint = $_ENV['S3_ENDPOINT'] ?? 'https://files-api.apps.misafirus.com';
        $accessKey = $_ENV['S3_ACCESS_KEY'] ?? '8d2b5f417f60ef4456765766';
        $secretKey = $_ENV['S3_SECRET_KEY'] ?? 'aabf96bc25a790c3ec944155ab6348fd0840e3';

        $this->s3Client = new S3Client([
            'version' => 'latest',
            'region' => $this->region,
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => $accessKey,
                'secret' => $secretKey,
            ],
        ]);
    }

    /**
     * POST /api/v1/upload
     * Dosya yükle (tek veya çoklu)
     */
    public function upload(): void
    {
        if (empty($_FILES['files'])) {
            Response::error('Dosya seçilmedi', 422);
            return;
        }

        $uploadedFiles = [];
        $errors = [];

        // Tek dosya veya çoklu dosya kontrolü
        $files = $_FILES['files'];
        $fileCount = is_array($files['name']) ? count($files['name']) : 1;

        for ($i = 0; $i < $fileCount; $i++) {
            if (is_array($files['name'])) {
                $fileName = $files['name'][$i];
                $fileTmpName = $files['tmp_name'][$i];
                $fileSize = $files['size'][$i];
                $fileError = $files['error'][$i];
            } else {
                $fileName = $files['name'];
                $fileTmpName = $files['tmp_name'];
                $fileSize = $files['size'];
                $fileError = $files['error'];
            }

            // Hata kontrolü
            if ($fileError !== UPLOAD_ERR_OK) {
                $errors[] = [
                    'file' => $fileName,
                    'error' => 'Dosya yükleme hatası: ' . $fileError
                ];
                continue;
            }

            // Dosya boyutu kontrolü (10MB)
            $maxSize = 10 * 1024 * 1024; // 10MB
            if ($fileSize > $maxSize) {
                $errors[] = [
                    'file' => $fileName,
                    'error' => 'Dosya boyutu çok büyük (max 10MB)'
                ];
                continue;
            }

            // Dosya uzantısı kontrolü
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            if (!in_array($fileExtension, $allowedExtensions)) {
                $errors[] = [
                    'file' => $fileName,
                    'error' => 'Desteklenmeyen dosya formatı'
                ];
                continue;
            }

            // Benzersiz dosya adı oluştur
            $uniqueFileName = uniqid() . '_' . time() . '.' . $fileExtension;
            $s3Key = 'uploads/' . date('Y/m/d') . '/' . $uniqueFileName;

            try {
                // S3'e yükle
                $result = $this->s3Client->putObject([
                    'Bucket' => $this->bucket,
                    'Key' => $s3Key,
                    'SourceFile' => $fileTmpName,
                    'ACL' => 'public-read',
                    'ContentType' => mime_content_type($fileTmpName),
                ]);

                $uploadedFiles[] = [
                    'original_name' => $fileName,
                    'file_name' => $uniqueFileName,
                    'url' => $result['ObjectURL'],
                    'size' => $fileSize,
                    'type' => $fileExtension,
                ];

            } catch (AwsException $e) {
                $errors[] = [
                    'file' => $fileName,
                    'error' => 'S3 yükleme hatası: ' . $e->getMessage()
                ];
            }
        }

        if (empty($uploadedFiles) && !empty($errors)) {
            Response::error('Dosya yüklenemedi', 500, ['errors' => $errors]);
            return;
        }

        Response::success([
            'files' => $uploadedFiles,
            'errors' => $errors,
            'total_uploaded' => count($uploadedFiles),
            'total_errors' => count($errors),
        ], 'Dosyalar başarıyla yüklendi', 201);
    }

    /**
     * DELETE /api/v1/upload
     * Dosya sil
     */
    public function delete(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['url'])) {
            Response::error('url parametresi zorunludur', 422);
            return;
        }

        // URL'den S3 key'i çıkar
        $url = $data['url'];
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '';
        
        // Bucket adını path'ten çıkar
        $pathParts = explode('/', ltrim($path, '/'));
        if ($pathParts[0] === $this->bucket) {
            array_shift($pathParts);
        }
        $s3Key = implode('/', $pathParts);

        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $s3Key,
            ]);

            Response::success(null, 'Dosya başarıyla silindi');

        } catch (AwsException $e) {
            Response::error('Dosya silinemedi: ' . $e->getMessage(), 500);
        }
    }
}
