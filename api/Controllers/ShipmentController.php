<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\ShipmentModel;
use App\Models\PackageModel;
use App\Models\TrackingEventModel;
use App\Config\Database;

class ShipmentController
{
    private ShipmentModel $model;

    public function __construct()
    {
        $this->model = new ShipmentModel(Database::getConnection());
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'shipment', 'read')) {
            jsonError('No tenés permiso para ver embarques', 403);
        }

        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $status = $_GET['status'] ?? null;

        $total = $this->model->count($status);
        $shipments = $this->model->listPaginated($limit, $offset, $status);

        jsonSuccess([
            'shipments' => $shipments,
            'pagination' => [
                'page'  => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    public function get(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'shipment', 'read')) {
            jsonError('No tenés permiso para ver embarques', 403);
        }

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            jsonError('ID de embarque inválido', 422);
        }

        $shipment = $this->model->findById($id);
        if ($shipment === null) {
            jsonError('Embarque no encontrado', 404);
        }

        $packages = $this->model->listPackages($id);

        jsonSuccess(['shipment' => $shipment, 'packages' => $packages]);
    }

    public function eligiblePackages(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'shipment', 'read')) {
            jsonError('No tenés permiso para ver embarques', 403);
        }

        $q = trim($_GET['q'] ?? '');
        $mode = $_GET['mode'] ?? null;
        $mode = ($mode === 'air' || $mode === 'sea') ? $mode : null;
        $packageModel = new PackageModel(Database::getConnection());
        $packages = $packageModel->findEligibleForShipment($q !== '' ? $q : null, $mode);

        jsonSuccess(['packages' => $packages]);
    }

    public function create(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('shipment_create', 10, 900);

        if (!hasPermission($payload, 'shipment', 'create')) {
            jsonError('No tenés permiso para crear embarques', 403);
        }

        $data = getJsonInput();

        $mode = ($data['mode'] ?? '') === 'sea' ? 'sea' : 'air';
        $code = $data['code'] ?? '';

        if ($code === '') {
            $code = $this->model->nextCode();
        }
        $code = sanitizeString($code, 50);

        $insertData = [
            'code' => $code,
            'mode' => $mode,
            'created_by' => (int) $payload['sub'],
        ];

        if (isset($data['departure_date'])) {
            $insertData['departure_date'] = sanitizeString($data['departure_date'], 10);
        }
        if (isset($data['arrival_date'])) {
            $insertData['arrival_date'] = sanitizeString($data['arrival_date'], 10);
        }
        if (isset($data['notes'])) {
            $insertData['notes'] = sanitizeString($data['notes'], 500);
        }

        $id = $this->model->create($insertData);
        $shipment = $this->model->findById($id);

        jsonSuccess(['shipment' => $shipment], 'Embarque creado', 201);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('shipment_update', 20, 900);

        if (!hasPermission($payload, 'shipment', 'edit')) {
            jsonError('No tenés permiso para editar embarques', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            jsonError('ID de embarque inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Embarque no encontrado', 404);
        }

        if ($existing['status'] !== 'draft') {
            jsonError('Solo se pueden editar embarques en borrador', 409);
        }

        $updateData = [];

        if (isset($data['code'])) {
            $updateData['code'] = sanitizeString($data['code'], 50);
        }
        if (isset($data['mode'])) {
            $updateData['mode'] = ($data['mode'] === 'sea') ? 'sea' : 'air';
        }
        if (array_key_exists('departure_date', $data)) {
            $updateData['departure_date'] = $data['departure_date'] !== null ? sanitizeString($data['departure_date'], 10) : null;
        }
        if (array_key_exists('arrival_date', $data)) {
            $updateData['arrival_date'] = $data['arrival_date'] !== null ? sanitizeString($data['arrival_date'], 10) : null;
        }
        if (array_key_exists('notes', $data)) {
            $updateData['notes'] = $data['notes'] !== null ? sanitizeString($data['notes'], 500) : null;
        }

        if (empty($updateData)) {
            jsonError('No hay datos para actualizar', 422);
        }

        $this->model->update($id, $updateData);
        $updated = $this->model->findById($id);

        jsonSuccess(['shipment' => $updated], 'Embarque actualizado');
    }

    public function delete(): never
    {
        requireMethod('DELETE');
        $payload = requireAuthMiddleware(true);
        rateLimit('shipment_delete', 5, 900);

        if (!hasPermission($payload, 'shipment', 'delete')) {
            jsonError('No tenés permiso para eliminar embarques', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            jsonError('ID de embarque inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Embarque no encontrado', 404);
        }

        if ($existing['status'] === 'in_transit') {
            jsonError('No se puede eliminar un embarque en tránsito', 409);
        }

        $this->model->delete($id);
        jsonSuccess(null, 'Embarque eliminado');
    }

    public function addPackages(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('shipment_add_packages', 20, 900);

        if (!hasPermission($payload, 'shipment', 'edit')) {
            jsonError('No tenés permiso para editar embarques', 403);
        }

        $data = getJsonInput();
        $shipmentId = (int) ($data['shipment_id'] ?? 0);
        $packageIds = $data['package_ids'] ?? [];

        if ($shipmentId <= 0 || !is_array($packageIds) || empty($packageIds)) {
            jsonError('shipment_id y package_ids requeridos', 422);
        }

        $shipment = $this->model->findById($shipmentId);
        if ($shipment === null) {
            jsonError('Embarque no encontrado', 404);
        }

        if ($shipment['status'] !== 'draft') {
            jsonError('Solo se pueden agregar paquetes a un embarque en borrador', 409);
        }

        $db = Database::getConnection();
        $packageModel = new PackageModel($db);

        $added = 0;
        $skipped = 0;

        $db->beginTransaction();
        try {
            foreach ($packageIds as $pid) {
                $packageId = (int) $pid;
                if ($packageId <= 0) {
                    continue;
                }

                $package = $packageModel->findById($packageId);
                if ($package === null || $package['status_name'] !== 'received') {
                    $skipped++;
                    continue;
                }

                if ($this->model->addPackage($shipmentId, $packageId)) {
                    $added++;
                } else {
                    $skipped++;
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            apiLog('ERROR', 'Error adding packages to shipment: ' . $e->getMessage());
            jsonError('Error al agregar paquetes al embarque', 500);
        }

        $updated = $this->model->findById($shipmentId);
        jsonSuccess(['shipment' => $updated], "{$added} paquetes agregados" . ($skipped > 0 ? ", {$skipped} omitidos" : ''));
    }

    public function removePackage(): never
    {
        requireMethod('DELETE');
        $payload = requireAuthMiddleware(true);
        rateLimit('shipment_remove_package', 20, 900);

        if (!hasPermission($payload, 'shipment', 'edit')) {
            jsonError('No tenés permiso para editar embarques', 403);
        }

        $data = getJsonInput();
        $shipmentId = (int) ($data['shipment_id'] ?? 0);
        $packageId  = (int) ($data['package_id'] ?? 0);

        if ($shipmentId <= 0 || $packageId <= 0) {
            jsonError('shipment_id y package_id requeridos', 422);
        }

        $shipment = $this->model->findById($shipmentId);
        if ($shipment === null) {
            jsonError('Embarque no encontrado', 404);
        }

        if ($shipment['status'] !== 'draft') {
            jsonError('Solo se pueden quitar paquetes de un embarque en borrador', 409);
        }

        $this->model->removePackage($shipmentId, $packageId);
        jsonSuccess(null, 'Paquete removido del embarque');
    }

    public function dispatch(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('shipment_dispatch', 10, 900);

        if (!hasPermission($payload, 'shipment', 'edit')) {
            jsonError('No tenés permiso para despachar embarques', 403);
        }

        $data = getJsonInput();
        $shipmentId = (int) ($data['shipment_id'] ?? 0);

        if ($shipmentId <= 0) {
            jsonError('ID de embarque inválido', 422);
        }

        $shipment = $this->model->findById($shipmentId);
        if ($shipment === null) {
            jsonError('Embarque no encontrado', 404);
        }

        if ($shipment['status'] !== 'draft') {
            jsonError('Solo se puede despachar un embarque en borrador', 409);
        }

        $packageCount = (int) $shipment['package_count'];
        if ($packageCount === 0) {
            jsonError('El embarque no tiene paquetes', 422);
        }

        $uniform = $this->model->getUniformPackageStatus($shipmentId);
        if ($uniform !== 'received') {
            jsonError('Todos los paquetes deben estar en estado Recibido en Miami para despachar', 409);
        }

        $db = Database::getConnection();
        $packageModel = new PackageModel($db);
        $trackingModel = new TrackingEventModel($db);
        $packages = $this->model->listPackages($shipmentId);

        $now = date('Y-m-d H:i:s');
        $inTransitStatusId = $packageModel->getStatusId('in_transit');
        $modeLabel = $shipment['mode'] === 'sea' ? 'marítimo' : 'aéreo';

        $db->beginTransaction();
        try {
            foreach ($packages as $pkg) {
                $packageModel->update((int) $pkg['id'], ['status_id' => $inTransitStatusId]);
                $trackingModel->create([
                    'package_id' => (int) $pkg['id'],
                    'status' => 'in_transit',
                    'description' => 'Despachado desde Miami por vía ' . $modeLabel . ' — embarque ' . $shipment['code'],
                    'recorded_at' => $now,
                    'created_by' => (int) $payload['sub'],
                ]);
            }

            $updateData = ['status' => 'in_transit'];
            if (!empty($data['departure_date'])) {
                $updateData['departure_date'] = sanitizeString($data['departure_date'], 10);
            }
            $this->model->update($shipmentId, $updateData);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            apiLog('ERROR', 'Error dispatching shipment: ' . $e->getMessage());
            jsonError('Error al despachar el embarque', 500);
        }

        $notifier = new \App\Services\PackageNotifierService($db);
        $descInTransit = 'Despachado desde Miami por vía ' . $modeLabel . ' — embarque ' . $shipment['code'];
        foreach ($packages as $pkg) {
            $notifier->notifyStatusChange($pkg, 'received', 'in_transit', $descInTransit);
        }

        $updatedShipment = $this->model->findById($shipmentId);
        jsonSuccess(['shipment' => $updatedShipment], 'Embarque despachado');
    }

    public function arrive(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('shipment_arrive', 10, 900);

        if (!hasPermission($payload, 'shipment', 'edit')) {
            jsonError('No tenés permiso para marcar llegada de embarques', 403);
        }

        $data = getJsonInput();
        $shipmentId = (int) ($data['shipment_id'] ?? 0);

        if ($shipmentId <= 0) {
            jsonError('ID de embarque inválido', 422);
        }

        $shipment = $this->model->findById($shipmentId);
        if ($shipment === null) {
            jsonError('Embarque no encontrado', 404);
        }

        if ($shipment['status'] !== 'in_transit') {
            jsonError('Solo se puede marcar la llegada de un embarque en tránsito', 409);
        }

        $uniform = $this->model->getUniformPackageStatus($shipmentId);
        if ($uniform !== 'in_transit') {
            jsonError('Todos los paquetes deben estar en tránsito para marcar la llegada', 409);
        }

        $db = Database::getConnection();
        $packageModel = new PackageModel($db);
        $trackingModel = new TrackingEventModel($db);
        $packages = $this->model->listPackages($shipmentId);

        $now = date('Y-m-d H:i:s');
        $inNicaraguaStatusId = $packageModel->getStatusId('in_nicaragua');

        $db->beginTransaction();
        try {
            foreach ($packages as $pkg) {
                $packageModel->update((int) $pkg['id'], ['status_id' => $inNicaraguaStatusId]);
                $trackingModel->create([
                    'package_id' => (int) $pkg['id'],
                    'status' => 'in_nicaragua',
                    'description' => 'Llegó a Nicaragua — embarque ' . $shipment['code'] . ' verificado, listo para entrega local',
                    'recorded_at' => $now,
                    'created_by' => (int) $payload['sub'],
                ]);
            }

            $updateData = ['status' => 'arrived'];
            if (!empty($data['arrival_date'])) {
                $updateData['arrival_date'] = sanitizeString($data['arrival_date'], 10);
            }
            $this->model->update($shipmentId, $updateData);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            apiLog('ERROR', 'Error marking shipment arrival: ' . $e->getMessage());
            jsonError('Error al marcar la llegada del embarque', 500);
        }

        $notifier = new \App\Services\PackageNotifierService($db);
        $descInNicaragua = 'Llegó a Nicaragua — embarque ' . $shipment['code'] . ' verificado, listo para entrega local';
        foreach ($packages as $pkg) {
            $notifier->notifyStatusChange($pkg, 'in_transit', 'in_nicaragua', $descInNicaragua);
        }

        $updatedShipment = $this->model->findById($shipmentId);
        jsonSuccess(['shipment' => $updatedShipment], 'Embarque llegado a Nicaragua');
    }
}