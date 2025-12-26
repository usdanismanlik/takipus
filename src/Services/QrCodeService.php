<?php

namespace Src\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\Label\LabelAlignment;
use Endroid\QrCode\Label\Font\NotoSans;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use Aws\S3\S3Client;
use Exception;

class QrCodeService
{
    private ?S3Client $s3Client = null;
    private string $bucket;

    public function __construct()
    {
        // Load environment variables for S3 if needed
        // Assuming Dotenv is already loaded in index.php
        $this->initS3();
    }

    private function initS3(): void
    {
        $useS3 = filter_var($_ENV['USE_S3'] ?? 'true', FILTER_VALIDATE_BOOLEAN);

        if ($useS3) {
            $this->bucket = $_ENV['S3_BUCKET'] ?? 'takipus';
            $endpoint = $_ENV['S3_ENDPOINT'] ?? 'https://files-api.apps.misafirus.com';
            $accessKey = $_ENV['S3_ACCESS_KEY'] ?? '8d2b5f417f60ef4456765766';
            $secretKey = $_ENV['S3_SECRET_KEY'] ?? 'aabf96bc25a790c3ec944155ab6348fd0840e3';
            $region = $_ENV['S3_REGION'] ?? 'us-east-1';

            $this->s3Client = new S3Client([
                'version' => 'latest',
                'region' => $region,
                'endpoint' => $endpoint,
                'use_path_style_endpoint' => true,
                'credentials' => [
                    'key' => $accessKey,
                    'secret' => $secretKey,
                ],
            ]);
        }
    }

    public function generateAndUpload(string $data, string $label1, string $code): string
    {
        // 1. Generate QR Code
        $result = Builder::create()
            ->writer(new PngWriter())
            ->writerOptions([])
            ->data($data)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
            ->size(300)
            ->margin(10)
            ->build();

        // 2. Create Canvas with Text (Using GD)
        // QR image dimensions
        $qrImage = imagecreatefromstring($result->getString());
        $qrWidth = imagesx($qrImage);
        $qrHeight = imagesy($qrImage);

        // Canvas dimensions (QR + Text area)
        $padding = 20;
        $textHeight = 80;
        $canvasWidth = $qrWidth + ($padding * 2);
        $canvasHeight = $qrHeight + $textHeight + ($padding * 2);

        $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $black = imagecolorallocate($canvas, 0, 0, 0);

        // Fill white background
        imagefill($canvas, 0, 0, $white);

        // Copy QR code to canvas
        imagecopy($canvas, $qrImage, $padding, $padding, 0, 0, $qrWidth, $qrHeight);

        // Add Text
        // Note: Using built-in font for simplicity as .ttf might not be available
        // Ideally we should use imagettftext if font file exists

        $font = 4; // Built-in font size

        // Center text 1 (Name)
        $text1Width = imagefontwidth($font) * strlen($label1);
        $x1 = ($canvasWidth - $text1Width) / 2;
        imagestring($canvas, $font, $x1, $qrHeight + $padding + 10, $label1, $black);

        // Center text 2 (Code)
        $text2Width = imagefontwidth($font) * strlen($code);
        $x2 = ($canvasWidth - $text2Width) / 2;
        imagestring($canvas, $font, $x2, $qrHeight + $padding + 35, $code, $black);

        // 3. Save to temp file
        $tempFile = sys_get_temp_dir() . '/' . uniqid('qr_') . '.jpg';
        imagejpeg($canvas, $tempFile, 90);

        // 4. Upload to S3
        $s3Key = 'uploads/qrcodes/' . date('Y/m/d') . '/' . uniqid() . '.jpg';

        try {
            if ($this->s3Client) {
                $result = $this->s3Client->putObject([
                    'Bucket' => $this->bucket,
                    'Key' => $s3Key,
                    'SourceFile' => $tempFile,
                    'ACL' => 'public-read',
                    'ContentType' => 'image/jpeg',
                ]);

                $url = $result['ObjectURL'];
            } else {
                throw new Exception("S3 Client not configured");
            }
        } finally {
            // Cleanup
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            imagedestroy($qrImage);
            imagedestroy($canvas);
        }

        return $url;
    }
}
