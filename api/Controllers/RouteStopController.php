<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\RouteStopModel;
use App\Models\PackageModel;
use App\Config\Database;

// ImageKit config
define('IMAGEKIT_PUBLIC_KEY', getenv('IMAGEKIT_PUBLIC_KEY') ?: '');
define('IMAGEKIT_PRIVATE_KEY', getenv('IMAGEKIT_PRIVATE_KEY') ?: '');
define('IMAGEKIT_URL_ENDPOINT', getenv('IMAGEKIT_URL_ENDPOINT') ?: '');

class RouteStopController
{
    private RouteStopModel $model;

    public function __construct()
    {
        $this->model = new RouteStopModel(Database::getConnection());
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'delivery_route', 'read')) {
            jsonError('No tenes permiso', 403);
        }

        $routeId = (int) ($_GET['route_id'] ?? 0);
        if ($routeId <= 0) jsonError('route_id requerido', 422);

        $stops = $this->model->listByRoute($routeId);
        jsonSuccess(['stops' => $stops]);
    }

    public function status(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('stop_status', 20, 900);

        if (!hasPermission($payload, 'delivery_route', 'edit')) {
            jsonError('No tenes permiso', 403);
        }

        $data = getJsonInput();
        $id = (int) ($data['id'] ?? 0);
        $newStatus = sanitizeString($data['status'] ?? '', 50);

        if ($id <= 0 || $newStatus === '') {
            jsonError('ID y status son requeridos', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) jsonError('Parada no encontrada', 404);

        $statusId = $this->model->getStatusId($newStatus);
        if ($statusId === null) jsonError('Estado invalido', 422);

        $updateData = ['status_id' => $statusId];
        if ($newStatus === 'visited') {
            $updateData['actual_arrival'] = date('H:i:s');
        }

        $this->model->update($id, $updateData);
        $stop = $this->model->findById($id);

        jsonSuccess(['stop' => $stop], 'Estado actualizado');
    }

    public function deliver(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('stop_deliver', 20, 900);

        if (!hasPermission($payload, 'delivery_route', 'edit')) {
            jsonError('No tenes permiso', 403);
        }

        $data = getJsonInput();
        $id = (int) ($data['id'] ?? 0);

        if ($id <= 0) jsonError('ID de parada requerido', 422);

        $existing = $this->model->findById($id);
        if ($existing === null) jsonError('Parada no encontrada', 404);

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $visitedStatusId = $this->model->getStatusId('visited');
            $updateData = [
                'status_id' => $visitedStatusId,
                'actual_arrival' => date('H:i:s'),
                'notes' => isset($data['notes']) ? sanitizeString((string) $data['notes'], 1000) : $existing['notes'],
            ];

            if (isset($data['signature_data'])) {
                $updateData['signature_data'] = sanitizeString((string) $data['signature_data'], 50000);
            }
            if (isset($data['photo_url'])) {
                $updateData['photo_url'] = sanitizeString((string) $data['photo_url'], 500);
            }
            if (isset($data['latitude'])) {
                $updateData['latitude'] = (float) $data['latitude'];
            }
            if (isset($data['longitude'])) {
                $updateData['longitude'] = (float) $data['longitude'];
            }

            $this->model->update($id, $updateData);

            if (!empty($existing['package_id'])) {
                $packageModel = new PackageModel($db);
                $packageModel->update((int) $existing['package_id'], [
                    'delivered_at' => date('Y-m-d H:i:s'),
                ]);

                $statusId = $packageModel->getStatusId('delivered');
                if ($statusId !== null) {
                    $packageModel->update((int) $existing['package_id'], ['status_id' => $statusId]);
                }
            }

            $db->commit();
            $stop = $this->model->findById($id);
            jsonSuccess(['stop' => $stop], 'Entrega registrada');
        } catch (\Throwable $e) {
            $db->rollBack();
            apiLog('ERROR', 'Error delivering stop: ' . $e->getMessage());
            jsonError('Error al registrar entrega', 500);
        }
    }

    public function signature(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('stop_signature', 20, 900);

        if (!hasPermission($payload, 'delivery_route', 'edit')) {
            jsonError('No tenes permiso', 403);
        }

        $data = getJsonInput();
        $id = (int) ($data['id'] ?? 0);
        $signatureData = sanitizeString($data['signature_data'] ?? '', 50000);

        if ($id <= 0 || $signatureData === '') {
            jsonError('ID y signature_data requeridos', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) jsonError('Parada no encontrada', 404);

        $this->model->update($id, ['signature_data' => $signatureData]);
        jsonSuccess(null, 'Firma guardada');
    }

    public function photo(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('stop_photo', 20, 900);

        if (!hasPermission($payload, 'delivery_route', 'edit')) {
            jsonError('No tenes permiso', 403);
        }

        $stopId = (int) ($_POST['id'] ?? 0);
        if ($stopId <= 0) jsonError('ID de parada requerido', 422);

        $existing = $this->model->findById($stopId);
        if ($existing === null) jsonError('Parada no encontrada', 404);

        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            jsonError('Archivo de foto requerido', 422);
        }

        $file = $_FILES['photo'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file['type'], $allowedTypes, true)) {
            jsonError('Tipo de archivo no permitido. Use JPG, PNG, WebP o GIF', 422);
        }

        $maxSize = 10 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            jsonError('La foto no debe superar los 10MB', 422);
        }

        if (empty(IMAGEKIT_PRIVATE_KEY)) {
            $this->model->update($stopId, ['photo_url' => '']);
            jsonSuccess(null, 'Photo upload no configurado (sin IMAGEKIT_PRIVATE_KEY)');
        }

        try {
            $uploadData = base64_encode(file_get_contents($file['tmp_name']));
            $ch = curl_init('https://upload.imagekit.io/api/v1/files/upload');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Basic ' . base64_encode(IMAGEKIT_PRIVATE_KEY . ':'),
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'file' => 'data:' . $file['type'] . ';base64,' . $uploadData,
                    'fileName' => 'stop_' . $stopId . '_' . time() . '.jpg',
                    'folder' => '/shipping/stops/',
                    'useUniqueFileName' => true,
                ]),
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                apiLog('WARN', "ImageKit upload failed for stop {$stopId}: HTTP {$httpCode}");
                jsonError('Error al subir la foto', 500);
            }

            $imageKitData = json_decode($response, true);
            $photoUrl = $imageKitData['url'] ?? $imageKitData['thumbnailUrl'] ?? '';

            $this->model->update($stopId, ['photo_url' => $photoUrl]);
            jsonSuccess(['photo_url' => $photoUrl], 'Foto subida');
        } catch (\Throwable $e) {
            apiLog('ERROR', 'Error uploading photo for stop: ' . $e->getMessage());
            jsonError('Error al subir la foto', 500);
        }
    }
}
