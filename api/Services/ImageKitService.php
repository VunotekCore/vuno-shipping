<?php
declare(strict_types=1);

namespace App\Services;

final class ImageKitService
{
    private const UPLOAD_URL = 'https://upload.imagekit.io/api/v1/files/upload';
    private const API_BASE = 'https://api.imagekit.io/v1';

    private string $privateKey;

    public function __construct(string $privateKey)
    {
        if ($privateKey === '') {
            throw new \RuntimeException('ImageKit private key not configured');
        }
        $this->privateKey = $privateKey;
    }

    public function upload(string $filePath, string $fileName, string $folder = ''): array
    {
        $mime = mime_content_type($filePath) ?: 'application/octet-stream';

        $data = [
            'file' => curl_file_create($filePath, $mime, $fileName),
            'fileName' => $fileName,
            'useUniqueFileName' => 'true',
        ];
        if ($folder !== '') {
            $data['folder'] = '/' . ltrim($folder, '/');
        }

        $ch = curl_init(self::UPLOAD_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: ' . $this->authHeader()],
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno) {
            throw new \RuntimeException('ImageKit upload error: ' . $error);
        }

        $result = json_decode((string) $response, true);
        if ($httpCode >= 400 || !is_array($result)) {
            throw new \RuntimeException(
                'ImageKit upload error (HTTP ' . $httpCode . '): ' . ($result['message'] ?? (string) $response)
            );
        }

        return $result;
    }

    public function delete(string $fileId): array
    {
        return $this->request('DELETE', '/files/' . $fileId);
    }

    private function authHeader(): string
    {
        return 'Basic ' . base64_encode($this->privateKey . ':');
    }

    private function request(string $method, string $endpoint): array
    {
        $ch = curl_init(self::API_BASE . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $this->authHeader(),
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        if ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string) $response, true);
        if ($httpCode >= 400) {
            throw new \RuntimeException(
                'ImageKit error (HTTP ' . $httpCode . '): ' . ($data['message'] ?? (string) $response)
            );
        }

        return is_array($data) ? $data : [];
    }
}
