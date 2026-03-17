<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Configuration\Configuration;

class CloudinaryService
{
    private UploadApi $uploadApi;
    private string $baseFolder;

    public function __construct(?string $baseFolder = null)
    {
        $this->configure();
        $this->uploadApi = new UploadApi();
        $this->baseFolder = trim($baseFolder ?? ($_ENV['CLOUDINARY_BASE_FOLDER'] ?? 'LaCanchitaDeLosPibes'), '/');
    }

    public function upload(string $filePath, string $folder, string $resourceType = 'image', array $options = []): array
    {
        $isRemoteUrl = (bool) preg_match('/^https?:\/\//i', $filePath);

        if (!$isRemoteUrl && !is_file($filePath)) {
            return [
                'success' => false,
                'message' => 'Archivo no encontrado para subir.'
            ];
        }

        $defaultOptions = [
            'folder' => $this->normalizeFolder($folder),
            'resource_type' => $resourceType,
            'overwrite' => false,
            'unique_filename' => true,
            'use_filename' => false,
        ];

        try {
            $result = $this->uploadApi->upload($filePath, array_merge($defaultOptions, $options));

            return [
                'success' => true,
                'url' => (string) ($result['secure_url'] ?? $result['url'] ?? ''),
                'public_id' => (string) ($result['public_id'] ?? ''),
                'raw' => $result,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Error al subir a Cloudinary.',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function configure(): void
    {
        $cloudName = trim((string) ($_ENV['CLOUDINARY_CLOUD_NAME'] ?? ''));
        $apiKey = trim((string) ($_ENV['CLOUDINARY_API_KEY'] ?? ''));
        $apiSecret = trim((string) ($_ENV['CLOUDINARY_API_SECRET'] ?? ''));

        if ($cloudName === '' || $apiKey === '' || $apiSecret === '') {
            throw new RuntimeException('Faltan credenciales de Cloudinary en .env');
        }

        Configuration::instance([
            'cloud' => [
                'cloud_name' => $cloudName,
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
            ],
            'url' => [
                'secure' => true,
            ],
        ]);
    }

    private function normalizeFolder(string $folder): string
    {
        $folder = trim($folder, '/');

        if ($folder === '') {
            return $this->baseFolder;
        }

        if ($folder === $this->baseFolder || strpos($folder, $this->baseFolder . '/') === 0) {
            return $folder;
        }

        return $this->baseFolder . '/' . $folder;
    }
}