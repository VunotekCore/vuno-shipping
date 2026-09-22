<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\RouteModel;
use App\Config\Database;

class RouteController
{
    private RouteModel $model;

    public function __construct()
    {
        $this->model = new RouteModel(Database::getConnection());
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'delivery_route', 'read')) {
            jsonError('No tenes permiso para ver rutas', 403);
        }

        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $limit    = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
        $offset   = ($page - 1) * $limit;

        $total = $this->model->count();
        $routes = $this->model->listPaginated($limit, $offset);

        if (!empty($routes)) {
            $zonesByRoute = [];
            $zoneRows = $this->model->getZonesForRoutes(array_column($routes, 'id'));
            foreach ($zoneRows as $zoneRow) {
                $zonesByRoute[(int) $zoneRow['route_id']][] = [
                    'id'   => (int) $zoneRow['id'],
                    'name' => $zoneRow['name'],
                    'lat'  => $zoneRow['lat'],
                    'lng'  => $zoneRow['lng'],
                ];
            }
            foreach ($routes as &$route) {
                $route['zones'] = $zonesByRoute[(int) $route['id']] ?? [];
            }
            unset($route);
        }

        jsonSuccess([
            'routes' => $routes,
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

        if (!hasPermission($payload, 'delivery_route', 'read')) {
            jsonError('No tenes permiso para ver rutas', 403);
        }

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) jsonError('ID de ruta invalido', 422);

        $route = $this->model->findById($id);
        if ($route === null) jsonError('Ruta no encontrada', 404);

        $route['zones'] = $this->model->getZones($id);

        jsonSuccess(['route' => $route]);
    }

    public function create(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('route_create', 10, 900);

        if (!hasPermission($payload, 'delivery_route', 'create')) {
            jsonError('No tenes permiso para crear rutas', 403);
        }

        $data = getJsonInput();

        $name = $data['name'] ?? '';
        if ($name === '') jsonError('El nombre es requerido', 422);

        $trajectory = isset($data['trajectory']) ? json_encode($data['trajectory']) : null;

        $insertData = [
            'name' => sanitizeString($name, 255),
            'description' => isset($data['description']) ? sanitizeString($data['description'], 1000) : null,
            'trajectory' => $trajectory,
            'color' => $data['color'] ?? '#22c55e',
            'default_driver_id' => isset($data['default_driver_id']) ? (int) $data['default_driver_id'] : null,
            'default_vehicle_id' => isset($data['default_vehicle_id']) ? (int) $data['default_vehicle_id'] : null,
            'start_latitude' => isset($data['start_latitude']) ? (float) $data['start_latitude'] : null,
            'start_longitude' => isset($data['start_longitude']) ? (float) $data['start_longitude'] : null,
            'end_latitude' => isset($data['end_latitude']) ? (float) $data['end_latitude'] : null,
            'end_longitude' => isset($data['end_longitude']) ? (float) $data['end_longitude'] : null,
        ];

        $this->validateDefaults($insertData['default_driver_id'], $insertData['default_vehicle_id']);

        $zoneIds = isset($data['zone_ids']) ? $this->validateZoneIds($data['zone_ids']) : null;

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $routeId = $this->model->create($insertData);

            if ($zoneIds !== null) {
                $this->model->setZones($routeId, $zoneIds);
            }

            $db->commit();
            $route = $this->model->findById($routeId);
            $route['zones'] = $this->model->getZones($routeId);
            jsonSuccess(['route' => $route], 'Ruta creada', 201);
        } catch (\Throwable $e) {
            $db->rollBack();
            apiLog('ERROR', 'Error creating route: ' . $e->getMessage());
            jsonError('Error al crear la ruta', 500);
        }
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('route_update', 20, 900);

        if (!hasPermission($payload, 'delivery_route', 'edit')) {
            jsonError('No tenes permiso para editar rutas', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);
        if ($id <= 0) jsonError('ID de ruta invalido', 422);

        $existing = $this->model->findById($id);
        if ($existing === null) jsonError('Ruta no encontrada', 404);

        $updateData = [];

        if (isset($data['name'])) $updateData['name'] = sanitizeString($data['name'], 255);
        if (array_key_exists('description', $data)) $updateData['description'] = $data['description'] !== null ? sanitizeString($data['description'], 1000) : null;
        if (array_key_exists('color', $data)) $updateData['color'] = $data['color'];
        if (array_key_exists('trajectory', $data)) $updateData['trajectory'] = is_array($data['trajectory']) ? json_encode($data['trajectory']) : $data['trajectory'];
        if (array_key_exists('default_driver_id', $data)) $updateData['default_driver_id'] = $data['default_driver_id'] !== null ? (int) $data['default_driver_id'] : null;
        if (array_key_exists('default_vehicle_id', $data)) $updateData['default_vehicle_id'] = $data['default_vehicle_id'] !== null ? (int) $data['default_vehicle_id'] : null;
        if (array_key_exists('start_latitude', $data)) $updateData['start_latitude'] = $data['start_latitude'] !== null ? (float) $data['start_latitude'] : null;
        if (array_key_exists('start_longitude', $data)) $updateData['start_longitude'] = $data['start_longitude'] !== null ? (float) $data['start_longitude'] : null;
        if (array_key_exists('end_latitude', $data)) $updateData['end_latitude'] = $data['end_latitude'] !== null ? (float) $data['end_latitude'] : null;
        if (array_key_exists('end_longitude', $data)) $updateData['end_longitude'] = $data['end_longitude'] !== null ? (float) $data['end_longitude'] : null;

        if (array_key_exists('default_driver_id', $updateData) || array_key_exists('default_vehicle_id', $updateData)) {
            $this->validateDefaults(
                $updateData['default_driver_id'] ?? (isset($existing['default_driver_id']) ? (int) $existing['default_driver_id'] : null),
                $updateData['default_vehicle_id'] ?? (isset($existing['default_vehicle_id']) ? (int) $existing['default_vehicle_id'] : null)
            );
        }

        $zoneIds = isset($data['zone_ids']) ? $this->validateZoneIds($data['zone_ids']) : null;

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            if (!empty($updateData)) {
                $this->model->update($id, $updateData);
            }

            if ($zoneIds !== null) {
                $this->model->setZones($id, $zoneIds);
            }

            $db->commit();
            $route = $this->model->findById($id);
            $route['zones'] = $this->model->getZones($id);
            jsonSuccess(['route' => $route], 'Ruta actualizada');
        } catch (\Throwable $e) {
            $db->rollBack();
            apiLog('ERROR', 'Error updating route: ' . $e->getMessage());
            jsonError('Error al actualizar la ruta', 500);
        }
    }

    public function delete(): never
    {
        requireMethod('DELETE');
        $payload = requireAuthMiddleware(true);
        rateLimit('route_delete', 5, 900);

        if (!hasPermission($payload, 'delivery_route', 'delete')) {
            jsonError('No tenes permiso para eliminar rutas', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);
        if ($id <= 0) jsonError('ID de ruta invalido', 422);

        $existing = $this->model->findById($id);
        if ($existing === null) jsonError('Ruta no encontrada', 404);

        $this->model->delete($id);
        jsonSuccess(null, 'Ruta eliminada');
    }

    private function validateZoneIds(mixed $zoneIds): array
    {
        if (!is_array($zoneIds)) {
            jsonError('zone_ids debe ser un arreglo', 422);
        }

        $seen = [];
        $clean = [];
        foreach ($zoneIds as $zoneId) {
            $zoneId = (int) $zoneId;
            if ($zoneId <= 0) continue;
            if (isset($seen[$zoneId])) {
                jsonError('Las zonas no pueden repetirse', 422);
            }
            $seen[$zoneId] = true;
            $clean[] = $zoneId;
        }

        return $clean;
    }

    private function validateDefaults(?int $driverId, ?int $vehicleId): void
    {
        $db = Database::getConnection();

        if ($driverId !== null) {
            $stmt = $db->prepare("
                SELECT r.slug FROM `user` u
                JOIN role r ON r.id = u.role_id
                WHERE u.id = :id LIMIT 1
            ");
            $stmt->execute(['id' => $driverId]);
            $roleSlug = $stmt->fetchColumn();
            if ($roleSlug === false) {
                jsonError('El conductor por defecto no existe', 422);
            }
            if ($roleSlug !== 'conductor') {
                jsonError('El usuario debe tener el rol conductor para ser asignado', 422);
            }
        }

        if ($vehicleId !== null) {
            $stmt = $db->prepare('SELECT id FROM vehicle WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $vehicleId]);
            if ($stmt->fetchColumn() === false) {
                jsonError('El vehiculo por defecto no existe', 422);
            }
        }
    }
}
