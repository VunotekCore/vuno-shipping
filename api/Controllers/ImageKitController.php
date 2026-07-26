<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\ImageKitService;

class ImageKitController
{
    private ImageKitService $service;

    private const ALLOWED_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/svg+xml',
    ];
    private const MAX_SIZE = 10 * 1024 * 1024;

    private const ALLOWED_FOLDERS = [
        'uploads',
        'company',
        'blog',
        'blog/og',
        'projects',
    ];

    public function __construct()
    {
        $config = config();
        $privateKey = $config['imagekit']['private_key'] ?? '';
        $this->service = new ImageKitService($privateKey);
    }

    public function upload(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('imagekit_upload', 20, 900);

        if (!hasPermission($payload, 'company', 'edit')) {
            jsonError('No tenés permiso para subir archivos', 403);
        }

        if (empty($_FILES['file'])) {
            jsonError('No file received');
        }

        $file = $_FILES['file'];
        $folder = $this->sanitizeFolder($_POST['folder'] ?? 'uploads');

        if ($file['error'] !== UPLOAD_ERR_OK) {
            jsonError('Upload error: ' . $this->uploadError($file['error']));
        }

        if ($file['size'] > self::MAX_SIZE) {
            jsonError('File exceeds 10MB limit');
        }

        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, self::ALLOWED_TYPES, true)) {
            jsonError('File type not allowed: ' . $mime);
        }

        try {
            $result = $this->service->upload($file['tmp_name'], $file['name'], $folder);
        } catch (\RuntimeException $e) {
            jsonError($e->getMessage());
        }

        jsonSuccess([
            'url'    => $result['url'] ?? '',
            'fileId' => $result['fileId'] ?? '',
            'name'   => $result['name'] ?? '',
        ]);
    }

    public function delete(): never
    {
        requireMethod('DELETE');
        $payload = requireAuthMiddleware(true);
        rateLimit('imagekit_delete', 10, 900);

        if (!hasPermission($payload, 'company', 'edit')) {
            jsonError('No tenés permiso para eliminar archivos', 403);
        }

        $data = getJsonInput();
        $fileId = $data['fileId'] ?? '';

        if ($fileId === '') {
            jsonError('fileId required');
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $fileId)) {
            jsonError('fileId inválido');
        }

        try {
            $this->service->delete($fileId);
        } catch (\RuntimeException $e) {
            jsonError($e->getMessage());
        }

        jsonSuccess(null, 'File deleted');
    }

    private function sanitizeFolder(string $folder): string
    {
        $folder = strtolower(trim($folder));
        $folder = preg_replace('/[^a-z0-9_\/-]/', '', $folder);
        $folder = preg_replace('/\/+/', '/', $folder);
        $folder = trim($folder, '/');

        if (!in_array($folder, self::ALLOWED_FOLDERS, true)) {
            $folder = 'uploads';
        }

        return $folder;
    }

    private function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL    => 'Incomplete upload',
            UPLOAD_ERR_NO_FILE    => 'No file selected',
            UPLOAD_ERR_NO_TMP_DIR => 'Temp directory not available',
            UPLOAD_ERR_CANT_WRITE => 'Error writing to disk',
            default               => 'Unknown error (' . $code . ')',
        };
    }
}
