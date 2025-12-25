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

            // Görsel dosyaları optimize et ve JPG'e çevir
            $isImage = in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'heic', 'heif', 'webp']);

            // Final dosya bilgileri (başlangıçta orijinal)
            $finalFilePath = $fileTmpName;
            $finalExtension = $fileExtension;
            $finalMimeType = $fileTmpName ? mime_content_type($fileTmpName) : '';

            if ($isImage) {
                // Optimize et ve her zaman JPG yolunu al
                $optimizedPath = $this->optimizeImage($fileTmpName, $fileExtension);

                // Eğer optimizasyon yapıldıysa (yol değiştiyse)
                if ($optimizedPath !== $fileTmpName) {
                    $finalFilePath = $optimizedPath;
                    $finalExtension = 'jpg'; // Artık kesinlikle jpg

                    // Dosya adını da güncelle (uzantıyı değiştir)
                    $uniqueFileName = pathinfo($uniqueFileName, PATHINFO_FILENAME) . '.jpg';

                    // Relative path'i de güncelle
                    $relativePath = 'uploads/' . date('Y/m/d') . '/' . $uniqueFileName;
                }
            }

            try {
                if ($this->useS3) {
                    // S3'e yükle (Final dosya ile)
                    $result = $this->s3Client->putObject([
                        'Bucket' => $this->bucket,
                        'Key' => $relativePath,
                        'SourceFile' => $finalFilePath,
                        'ACL' => 'public-read',
                        'ContentType' => mime_content_type($finalFilePath),
                    ]);

                    $uploadedFiles[] = [
                        'original_name' => $fileName,
                        'file_name' => $uniqueFileName,
                        'url' => $result['ObjectURL'],
                        'size' => filesize($finalFilePath),
                        'type' => $finalExtension,
                    ];
                } else {
                    // Local'e yükle
                    $uploadDir = rtrim($this->localUploadUrl, '/') . '/' . date('Y/m/d'); // URL değil DIR olmalı, altta fixliyorum
                    $uploadDirReal = rtrim($this->localUploadDir, '/') . '/' . date('Y/m/d');

                    // Klasörü oluştur
                    if (!is_dir($uploadDirReal)) {
                        mkdir($uploadDirReal, 0755, true);
                    }

                    $localFilePath = $uploadDirReal . '/' . $uniqueFileName;

                    // optimizeImage zaten dosyayı tmp dizininde veya overwrite ederek düzenledi
                    // Dosyayı hedef dizine taşıyalım
                    // move_uploaded_file sadece POST ile gelen dosyalarda çalışır.
                    // Eğer optimizeImage yeni bir dosya oluşturduysa rename kullanmalıyız.
                    // Ancak optimizeImage orjinal tmp dosyasının üzerine yazıyorsa move_uploaded_file çalışabilir mi?
                    // GD/Imagick ile oluşturulan dosya artık "uploaded file" statüsünde olmayabilir.
                    // Bu yüzden copy/rename + unlink daha güvenli.

                    if (rename($finalFilePath, $localFilePath)) {
                        // move_uploaded_file yerine rename kullandık çünkü dosya optimize edilmiş olabilir
                        // ve PHP'nin tmp dizininde bizim oluşturduğumuz bir dosya olabilir.

                        // Eğer orjinal dosya hala duruyorsa ve optimize edilmediyse?
                        // $finalFilePath == $fileTmpName ise, bu orjinal upload dosyasıdır.
                        // Ancak optimizeImage içinde override ediyoruz. O yüzden rename güvenlidir. 
                        // Sadece permission hatası almamak lazım.
                        // Alternatif: copy + unlink

                        $fileUrl = rtrim($this->localUploadUrl, '/') . '/' . date('Y/m/d') . '/' . $uniqueFileName;

                        $uploadedFiles[] = [
                            'original_name' => $fileName,
                            'file_name' => $uniqueFileName,
                            'url' => $fileUrl,
                            'size' => filesize($localFilePath),
                            'type' => $finalExtension,
                        ];
                    } else {
                        $errors[] = [
                            'file' => $fileName,
                            'error' => 'Dosya yükleme hatası: Dosya taşınamadı'
                        ];
                    }
                }

                // Temp dosyaları temizle (eğer optimize edildiyse ve hala duruyorsa)
                if ($finalFilePath !== $fileTmpName && file_exists($finalFilePath)) {
                    @unlink($finalFilePath);
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
    /**
     * Görsel optimizasyonu - boyut küçültme, formata çevirme (JPG) ve metadata temizleme
     * Desteklenen formatlar: JPG, PNG, GIF, WEBP, HEIC (Imagick varsa)
     */
    private function optimizeImage(string $filePath, string $extension): string
    {
        $maxWidth = 1920;
        $maxHeight = 1920;
        $quality = 80;

        // Çıktı her zaman JPG olacak
        // Eğer filePath zaten .jpg ile bitiyorsa aynı yolu kullan, değilse .jpg ekle/değiştir
        $outputPath = $filePath;
        if (strtolower($extension) !== 'jpg' && strtolower($extension) !== 'jpeg') {
            $outputPath = preg_replace('/\.' . preg_quote($extension, '/') . '$/i', '.jpg', $filePath);
            // Regex başarısız olursa veya aynı kalırsa (örn uzantı yoksa) sonuna ekle
            if ($outputPath === $filePath) {
                $outputPath .= '.jpg';
            }
        }

        // 1. Imagick Dene (HEIC ve daha kaliteli dönüşüm için öncelikli)
        if (extension_loaded('imagick')) {
            try {
                $imagick = new \Imagick();
                $imagick->readImage($filePath);

                // Eğer HEIC ise veya çoklu frame varsa (GIF), sadece ilk kareyi al
                // (Genellikle profil/belge fotosu olduğu için animasyon gerekmez)
                // Ama GIF animasyonunu korumak istersek bu adımı atlarız. 
                // Kullanıcı "hepsini jpg yap" dediği için animasyon ölecek, bu beklenen bir durum.
                $imagick = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

                // Boyutlandır
                $width = $imagick->getImageWidth();
                $height = $imagick->getImageHeight();

                if ($width > $maxWidth || $height > $maxHeight) {
                    $imagick->resizeImage($maxWidth, $maxHeight, \Imagick::FILTER_LANCZOS, 1, true);
                }

                // Formatı JPG yap
                $imagick->setImageFormat('jpg');
                $imagick->setImageCompression(\Imagick::COMPRESSION_JPEG);
                $imagick->setImageCompressionQuality($quality);

                // Metadata temizle
                $imagick->stripImage();

                // Kaydet
                $imagick->writeImage($outputPath);
                $imagick->clear();
                $imagick->destroy();

                // Eğer uzantı değiştiyse eski dosyayı sil
                if ($filePath !== $outputPath && file_exists($filePath)) {
                    @unlink($filePath);
                }

                return $outputPath;

            } catch (\Exception $e) {
                error_log('Imagick optimizasyon hatası: ' . $e->getMessage() . ' - GD deneniyor...');
                // Imagick başarısız olursa GD'ye düş
            }
        }

        // 2. GD Kütüphanesi (Fallback)
        // HEIC GD ile native desteklenmez, o yüzden HEIC ise ve Imagick yoksa işlem yapamayız.
        if (in_array($extension, ['heic', 'heif'])) {
            error_log('GD HEIC desteklemiyor ve Imagick yok. Dosya olduğu gibi bırakılıyor.');
            return $filePath;
        }

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
            case 'webp':
                $image = @imagecreatefromwebp($filePath);
                break;
        }

        if (!$image) {
            return $filePath;
        }

        // Boyutları al
        $width = imagesx($image);
        $height = imagesy($image);

        // Yeni boyutları hesapla
        $newWidth = $width;
        $newHeight = $height;

        if ($width > $maxWidth || $height > $maxHeight) {
            $ratio = min($maxWidth / $width, $maxHeight / $height);
            $newWidth = (int) ($width * $ratio);
            $newHeight = (int) ($height * $ratio);
        }

        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        // Şeffaflık (PNG/GIF) -> Beyaz Arkaplan (JPG için)
        $white = imagecolorallocate($newImage, 255, 255, 255);
        imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $white);

        // Resmi kopyala ve yeniden boyutlandır
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // JPG Olarak Kaydet
        imagejpeg($newImage, $outputPath, $quality);

        // Temizlik
        imagedestroy($image);
        imagedestroy($newImage);

        // Eğer uzantı değiştiyse eski dosyayı sil
        if ($filePath !== $outputPath && file_exists($filePath)) {
            @unlink($filePath);
        }

        return $outputPath;
    }
}
