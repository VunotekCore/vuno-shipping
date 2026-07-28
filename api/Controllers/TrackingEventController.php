<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\TrackingEventModel;
use App\Models\PackageModel;
use App\Config\Database;

class TrackingEventController
{
    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'package', 'read')) {
            jsonError('No tenés permiso para ver eventos de tracking', 403);
        }

        $packageId = (int) ($_GET['package_id'] ?? 0);
        if ($packageId <= 0) {
            jsonError('ID de paquete requerido', 422);
        }

        $model = new TrackingEventModel(Database::getConnection());
        $events = $model->listByPackage($packageId);

        jsonSuccess(['events' => $events]);
    }

    public function create(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('tracking_event_create', 10, 900);

        if (!hasPermission($payload, 'package', 'edit')) {
            jsonError('No tenés permiso para crear eventos de tracking', 403);
        }

        $data = getJsonInput();

        $packageId = (int) ($data['package_id'] ?? 0);
        $status = $data['status'] ?? '';

        if ($packageId <= 0 || $status === '') {
            jsonError('package_id y status son requeridos', 422);
        }

        $packageModel = new PackageModel(Database::getConnection());
        $package = $packageModel->findById($packageId);
        if ($package === null) {
            jsonError('Paquete no encontrado', 404);
        }

        $currentStatusName = $packageModel->getStatusName((int) $package['status_id']);
        if ($currentStatusName !== null && !$packageModel->isValidTransition($currentStatusName, $status)) {
            jsonError("No se puede cambiar de '{$currentStatusName}' a '{$status}'", 409);
        }

        $model = new TrackingEventModel(Database::getConnection());
        $id = $model->create([
            'package_id' => $packageId,
            'status' => sanitizeString($status, 50),
            'description' => isset($data['description']) ? sanitizeString($data['description'], 500) : null,
            'latitude' => isset($data['latitude']) ? (float) $data['latitude'] : null,
            'longitude' => isset($data['longitude']) ? (float) $data['longitude'] : null,
            'location_name' => isset($data['location_name']) ? sanitizeString($data['location_name'], 255) : null,
            'photo_url' => isset($data['photo_url']) ? sanitizeString($data['photo_url'], 500) : null,
            'recorded_at' => $data['recorded_at'] ?? date('Y-m-d H:i:s'),
            'created_by' => (int) $payload['sub'],
        ]);

        jsonSuccess(['id' => $id], 'Evento de tracking creado', 201);
    }
}
