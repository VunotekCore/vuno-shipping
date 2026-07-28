<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\VehicleModel;
use App\Config\Database;

class VehicleController
{
    private VehicleModel $model;

    public function __construct()
    {
        $this->model = new VehicleModel(Database::getConnection());
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'vehicle', 'read')) {
            jsonError('No tenés permiso para ver vehículos', 403);
        }

        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->model->count();
        $vehicles = $this->model->listPaginated($limit, $offset);

        jsonSuccess([
            'vehicles' => $vehicles,
            'pagination' => [
                'page'  => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    public function listAll(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'vehicle', 'read')) {
            jsonError('No tenés permiso para ver vehículos', 403);
        }

        $vehicles = $this->model->listAll();
        jsonSuccess(['vehicles' => $vehicles]);
    }

    public function create(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('vehicle_create', 10, 900);

        if (!hasPermission($payload, 'vehicle', 'create')) {
            jsonError('No tenés permiso para crear vehículos', 403);
        }

        $data = getJsonInput();

        $plate = $data['plate'] ?? '';
        if ($plate === '') {
            jsonError('La placa es requerida', 422);
        }

        $insertData = ['plate' => sanitizeString($plate, 20)];

        if (isset($data['brand'])) {
            $insertData['brand'] = sanitizeString($data['brand'], 100);
        }
        if (isset($data['model'])) {
            $insertData['model'] = sanitizeString($data['model'], 100);
        }
        if (isset($data['year'])) {
            $insertData['year'] = (int) $data['year'];
        }
        if (isset($data['color'])) {
            $insertData['color'] = sanitizeString($data['color'], 50);
        }
        if (isset($data['fuel_type'])) {
            $insertData['fuel_type'] = sanitizeString($data['fuel_type'], 20);
        }
        if (isset($data['capacity_kg'])) {
            $insertData['capacity_kg'] = (float) $data['capacity_kg'];
        }
        if (isset($data['capacity_m3'])) {
            $insertData['capacity_m3'] = (float) $data['capacity_m3'];
        }
        if (isset($data['current_driver_id'])) {
            $driverId = (int) $data['current_driver_id'];
            if (!$this->model->validateDriverExists($driverId)) {
                jsonError('El conductor especificado no existe', 422);
            }
            $insertData['current_driver_id'] = $driverId;
        }
        if (isset($data['status_id'])) {
            $statusId = (int) $data['status_id'];
            if (!$this->model->validateStatusExists($statusId)) {
                jsonError('El estado especificado no existe', 422);
            }
            $insertData['status_id'] = $statusId;
        }
        if (isset($data['notes'])) {
            $insertData['notes'] = sanitizeString($data['notes'], 500);
        }

        $id = $this->model->create($insertData);
        $vehicle = $this->model->findById($id);

        jsonSuccess(['vehicle' => $vehicle], 'Vehículo creado', 201);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('vehicle_update', 20, 900);

        if (!hasPermission($payload, 'vehicle', 'edit')) {
            jsonError('No tenés permiso para editar vehículos', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            jsonError('ID de vehículo inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Vehículo no encontrado', 404);
        }

        $updateData = [];

        if (isset($data['plate'])) {
            $updateData['plate'] = sanitizeString($data['plate'], 20);
        }
        if (array_key_exists('brand', $data)) {
            $updateData['brand'] = $data['brand'] !== null ? sanitizeString($data['brand'], 100) : null;
        }
        if (array_key_exists('model', $data)) {
            $updateData['model'] = $data['model'] !== null ? sanitizeString($data['model'], 100) : null;
        }
        if (array_key_exists('year', $data)) {
            $updateData['year'] = $data['year'] !== null ? (int) $data['year'] : null;
        }
        if (array_key_exists('color', $data)) {
            $updateData['color'] = $data['color'] !== null ? sanitizeString($data['color'], 50) : null;
        }
        if (array_key_exists('fuel_type', $data)) {
            $updateData['fuel_type'] = $data['fuel_type'] !== null ? sanitizeString($data['fuel_type'], 20) : null;
        }
        if (array_key_exists('capacity_kg', $data)) {
            $updateData['capacity_kg'] = $data['capacity_kg'] !== null ? (float) $data['capacity_kg'] : null;
        }
        if (array_key_exists('capacity_m3', $data)) {
            $updateData['capacity_m3'] = $data['capacity_m3'] !== null ? (float) $data['capacity_m3'] : null;
        }
        if (array_key_exists('current_driver_id', $data)) {
            if ($data['current_driver_id'] !== null) {
                $driverId = (int) $data['current_driver_id'];
                if (!$this->model->validateDriverExists($driverId)) {
                    jsonError('El conductor especificado no existe', 422);
                }
                $updateData['current_driver_id'] = $driverId;
            } else {
                $updateData['current_driver_id'] = null;
            }
        }
        if (array_key_exists('status_id', $data)) {
            if ($data['status_id'] !== null) {
                $statusId = (int) $data['status_id'];
                if (!$this->model->validateStatusExists($statusId)) {
                    jsonError('El estado especificado no existe', 422);
                }
                $updateData['status_id'] = $statusId;
            } else {
                $updateData['status_id'] = null;
            }
        }
        if (array_key_exists('notes', $data)) {
            $updateData['notes'] = $data['notes'] !== null ? sanitizeString($data['notes'], 500) : null;
        }

        if (empty($updateData)) {
            jsonError('No hay datos para actualizar', 422);
        }

        $this->model->update($id, $updateData);
        $updated = $this->model->findById($id);

        jsonSuccess(['vehicle' => $updated], 'Vehículo actualizado');
    }

    public function delete(): never
    {
        requireMethod('DELETE');
        $payload = requireAuthMiddleware(true);
        rateLimit('vehicle_delete', 5, 900);

        if (!hasPermission($payload, 'vehicle', 'delete')) {
            jsonError('No tenés permiso para eliminar vehículos', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            jsonError('ID de vehículo inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Vehículo no encontrado', 404);
        }

        $this->model->delete($id);
        jsonSuccess(null, 'Vehículo eliminado');
    }
}
