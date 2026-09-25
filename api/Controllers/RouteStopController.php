<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\RouteStopModel;
use App\Services\PackageDeliveryService;
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

        $runId = (int) ($_GET['run_id'] ?? 0);
        if ($runId <= 0) jsonError('run_id requerido', 422);

        $stops = $this->model->listByRun($runId);
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

        $this->assertStopOwnership($existing, $payload);
        $this->assertRunActive((int) $existing['id']);

        $statusId = $this->model->getStatusId($newStatus);
        if ($statusId === null) jsonError('Estado invalido', 422);

        if ($newStatus === 'delivered') {
            jsonError('Para registrar una entrega usa el endpoint de entrega', 409);
        }

        $updateData = ['status_id' => $statusId];

        if (in_array($newStatus, ['skipped', 'failed'], true) && isset($data['notes'])) {
            $updateData['notes'] = sanitizeString((string) $data['notes'], 1000);
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

        $this->assertStopOwnership($existing, $payload);
        $this->assertRunActive((int) $existing['id']);

        if (!empty($existing['signature_data']) && !preg_match('#^data:image/(png|jpeg|jpg|webp);base64,#i', $existing['signature_data'])) {
            jsonError('Firma invalida', 422);
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $deliveredStatusId = $this->model->getStatusId('delivered');
            if ($deliveredStatusId === null) throw new \RuntimeException('Stop status delivered not found');

            $notes = isset($data['notes']) ? sanitizeString((string) $data['notes'], 1000) : $existing['notes'];

            $updateData = [
                'status_id' => $deliveredStatusId,
                'actual_arrival' => date('H:i:s'),
                'notes' => $notes,
            ];

            if (isset($data['latitude']) && isset($data['longitude']) && isValidNicaraguaCoord($data['latitude'], $data['longitude'])) {
                $updateData['latitude'] = (float) $data['latitude'];
                $updateData['longitude'] = (float) $data['longitude'];
            }

            $this->model->update($id, $updateData);

            $delivery = null;
            if (!empty($existing['package_id'])) {
                $svc = new PackageDeliveryService($db);
                $delivery = $svc->markDelivered(
                    (int) $existing['package_id'],
                    (int) $payload['sub'],
                    [
                        'description' => $notes,
                        'latitude' => $updateData['latitude'] ?? null,
                        'longitude' => $updateData['longitude'] ?? null,
                        'photo_url' => $existing['photo_url'] ?? null,
                    ]
                );
            }

            $db->commit();

            if ($delivery !== null && !$delivery['already_delivered'] && !empty($delivery['package'])) {
                $svc->notifyDelivered($delivery['package'], $delivery['old_status'], $notes);
            }

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

        if (!preg_match('#^data:image/(png|jpeg|jpg|webp);base64,#i', $signatureData)) {
            jsonError('Formato de firma invalido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) jsonError('Parada no encontrada', 404);

        $this->assertStopOwnership($existing, $payload);
        $this->assertRunActive((int) $existing['id']);

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

        $this->assertStopOwnership($existing, $payload);
        $this->assertRunActive((int) $existing['id']);

        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            jsonError('Archivo de foto requerido', 422);
        }

        $file = $_FILES['photo'];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($allowedTypes[$detectedType])) {
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
                    'file' => 'data:' . $detectedType . ';base64,' . $uploadData,
                    'fileName' => 'stop_' . $stopId . '_' . time() . '.' . $allowedTypes[$detectedType],
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

    private function assertRunActive(int $stopId): void
    {
        $runStatus = $this->model->getRunStatusByStop($stopId);
        if ($runStatus !== 'active') {
            jsonError('El despacho debe estar en estado "active" para registrar entregas o acciones', 409);
        }
    }

    private function assertStopOwnership(array $stop, array $payload): void
    {
        $userId = (int) ($payload['sub'] ?? 0);
        $roleSlug = (string) ($payload['role_slug'] ?? '');

        if ($roleSlug !== 'conductor') {
            return;
        }

        $driverId = $this->model->getRunDriverId((int) $stop['id']);
        if ($driverId === null || $driverId !== $userId) {
            jsonError('Esta parada no pertenece a tu despacho', 403);
        }
    }
}
