<?php

namespace Src\Controllers;

use Src\Helpers\Response;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class FileUploadController
{
    private ?S3Client $s3Client = null;
    private string $bucket;
    private string $region;
    private bool $useS3;
    private string $localUploadDir;
    private string $localUploadUrl;

    public function __construct()
    {
        $this->useS3 = filter_var($_ENV['USE_S3'] ?? 'true', FILTER_VALIDATE_BOOLEAN);

        // Local upload directory - relative path'i absolute'a çevir
        $localDir = $_ENV['LOCAL_UPLOAD_DIR'] ?? 'uploads';
        if (!str_starts_with($localDir, '/')) {
            // Relative path ise, public root'a göre absolute yap
            // __DIR__ = /var/www/html/src/Controllers
            // __DIR__ . '/../../public' = /var/www/html/public
            $localDir = __DIR__ . '/../../public/' . $localDir;
        }
        $this->localUploadDir = $localDir;
        $this->localUploadUrl = $_ENV['LOCAL_UPLOAD_URL'] ?? 'https://takipus-api.apps.misafirus.com/uploads';

        if ($this->useS3) {
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
    }

    /**
     * POST /api/v1/upload
     * Dosya yükle (tek veya çoklu)
     */
    public function upload(): void
    {
        // Debug logging
        error_log('=== UPLOAD DEBUG ===');
        error_log('POST: ' . json_encode($_POST));
        error_log('FILES: ' . json_encode($_FILES));
        error_log('Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
        error_log('====================');

        if (empty($_FILES['files'])) {
            error_log('ERROR: No files uploaded');
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
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'heic', 'heif', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
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
            $relativePath = 'uploads/' . date('Y/m/d') . '/' . $uniqueFileName;

            // Görsel dosyaları optimize et
            $isImage = in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'heic', 'heif']);
            if ($isImage) {
                $fileTmpName = $this->optimizeImage($fileTmpName, $fileExtension);
                $fileSize = filesize($fileTmpName); // Yeni boyutu al
            }

            try {
                if ($this->useS3) {
                    // S3'e yükle
                    $result = $this->s3Client->putObject([
                        'Bucket' => $this->bucket,
                        'Key' => $relativePath,
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
                } else {
                    // Local'e yükle
                    $uploadDir = rtrim($this->localUploadDir, '/') . '/' . date('Y/m/d');

                    // Klasörü oluştur
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $localFilePath = $uploadDir . '/' . $uniqueFileName;

                    if (move_uploaded_file($fileTmpName, $localFilePath)) {
                        $fileUrl = rtrim($this->localUploadUrl, '/') . '/' . date('Y/m/d') . '/' . $uniqueFileName;

                        $uploadedFiles[] = [
                            'original_name' => $fileName,
                            'file_name' => $uniqueFileName,
                            'url' => $fileUrl,
                            'size' => $fileSize,
                            'type' => $fileExtension,
                        ];
                    } else {
                        $errors[] = [
                            'file' => $fileName,
                            'error' => 'Dosya yükleme hatası: Dosya taşınamadı'
                        ];
                    }
                }

            } catch (AwsException $e) {
                $errors[] = [
                    'file' => $fileName,
                    'error' => 'S3 yükleme hatası: ' . $e->getMessage()
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'file' => $fileName,
                    'error' => 'Yükleme hatası: ' . $e->getMessage()
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

        $url = $data['url'];
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '';

        try {
            if ($this->useS3) {
                // S3'ten sil
                // Bucket adını path'ten çıkar
                $pathParts = explode('/', ltrim($path, '/'));
                if ($pathParts[0] === $this->bucket) {
                    array_shift($pathParts);
                }
                $s3Key = implode('/', $pathParts);

                $this->s3Client->deleteObject([
                    'Bucket' => $this->bucket,
                    'Key' => $s3Key,
                ]);
            } else {
                // Local'den sil
                $localFilePath = $this->localUploadDir . $path;

                if (file_exists($localFilePath)) {
                    unlink($localFilePath);
                } else {
                    Response::error('Dosya bulunamadı', 404);
                    return;
                }
            }

            Response::success(null, 'Dosya başarıyla silindi');

        } catch (AwsException $e) {
            Response::error('S3 dosya silinemedi: ' . $e->getMessage(), 500);
        } catch (\Exception $e) {
            Response::error('Dosya silinemedi: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Görsel optimizasyonu - boyut küçültme ve metadata temizleme
     */
    private function optimizeImage(string $filePath, string $extension): string
    {
        // HEIC/HEIF için şimdilik optimizasyon yapma (GD desteklemiyor)
        if (in_array($extension, ['heic', 'heif'])) {
            return $filePath;
        }

        $maxWidth = 1920;  // Max genişlik
        $maxHeight = 1920; // Max yükseklik
        $quality = 85;     // JPEG kalitesi (0-100)

        // Görseli yükle
        $image = null;
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $image = @imagecreatefromjpeg($filePath);
                break;
            case 'png':
                $image = @imagecreatefrompng($filePath);
                break;
            case 'gif':
                $image = @imagecreatefromgif($filePath);
                break;
        }

        if (!$image) {
            // Görsel yüklenemezse orijinali döndür
            return $filePath;
        }

        // Mevcut boyutları al
        $width = imagesx($image);
        $height = imagesy($image);

        // Yeniden boyutlandırma gerekli mi?
        if ($width <= $maxWidth && $height <= $maxHeight) {
            // Boyut uygun ama yine de metadata temizle ve kaliteyi düşür
            $outputPath = $filePath . '.optimized.jpg';
            imagejpeg($image, $outputPath, $quality);
            imagedestroy($image);

            // Eski dosyayı sil, yenisini yerine koy
            unlink($filePath);
            rename($outputPath, $filePath);

            return $filePath;
        }

        // Aspect ratio'yu koru
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = (int) ($width * $ratio);
        $newHeight = (int) ($height * $ratio);

        // Yeni görsel oluştur
        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        // PNG için transparency koru
        if ($extension === 'png') {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Yeniden boyutlandır
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Kaydet (her zaman JPEG olarak, metadata'sız)
        $outputPath = $filePath . '.optimized.jpg';
        imagejpeg($newImage, $outputPath, $quality);

        // Belleği temizle
        imagedestroy($image);
        imagedestroy($newImage);

        // Eski dosyayı sil, yenisini yerine koy
        unlink($filePath);
        rename($outputPath, $filePath);

        return $filePath;
    }
}
