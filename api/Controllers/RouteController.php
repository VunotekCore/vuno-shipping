<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\RouteModel;
use App\Models\RouteStopModel;
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
        $status   = $_GET['status'] ?? null;
        $driverId = isset($_GET['driver_id']) ? (int) $_GET['driver_id'] : null;
        $date     = $_GET['date'] ?? null;

        $total  = $this->model->count($status, $driverId, $date);
        $routes = $this->model->listPaginated($limit, $offset, $status, $driverId, $date);

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

        $stopModel = new RouteStopModel(Database::getConnection());
        $stops  = $stopModel->listByRoute($id);
        $zones  = $this->model->getZones($id);

        $route['stops'] = $stops;
        $route['zones'] = $zones;

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

        $statusId = $this->model->getStatusId('draft');

        $trajectory = isset($data['trajectory']) ? json_encode($data['trajectory']) : null;

        $insertData = [
            'name' => sanitizeString($name, 255),
            'description' => isset($data['description']) ? sanitizeString($data['description'], 1000) : null,
            'assigned_to' => isset($data['assigned_to']) ? (int) $data['assigned_to'] : null,
            'vehicle_id' => isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : null,
            'scheduled_date' => $data['scheduled_date'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'status_id' => $statusId,
            'trajectory' => $trajectory,
            'color' => $data['color'] ?? '#22c55e',
            'start_latitude' => isset($data['start_latitude']) ? (float) $data['start_latitude'] : null,
            'start_longitude' => isset($data['start_longitude']) ? (float) $data['start_longitude'] : null,
            'end_latitude' => isset($data['end_latitude']) ? (float) $data['end_latitude'] : null,
            'end_longitude' => isset($data['end_longitude']) ? (float) $data['end_longitude'] : null,
        ];

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $routeId = $this->model->create($insertData);

            if (isset($data['zone_ids']) && is_array($data['zone_ids'])) {
                $this->model->setZones($routeId, $data['zone_ids']);
            }

            $db->commit();
            $route = $this->model->findById($routeId);
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
        if (array_key_exists('assigned_to', $data)) $updateData['assigned_to'] = $data['assigned_to'] !== null ? (int) $data['assigned_to'] : null;
        if (array_key_exists('vehicle_id', $data)) $updateData['vehicle_id'] = $data['vehicle_id'] !== null ? (int) $data['vehicle_id'] : null;
        if (array_key_exists('scheduled_date', $data)) $updateData['scheduled_date'] = $data['scheduled_date'];
        if (array_key_exists('start_time', $data)) $updateData['start_time'] = $data['start_time'];
        if (array_key_exists('end_time', $data)) $updateData['end_time'] = $data['end_time'];
        if (array_key_exists('color', $data)) $updateData['color'] = $data['color'];
        if (array_key_exists('trajectory', $data)) $updateData['trajectory'] = is_array($data['trajectory']) ? json_encode($data['trajectory']) : $data['trajectory'];
        if (array_key_exists('start_latitude', $data)) $updateData['start_latitude'] = $data['start_latitude'] !== null ? (float) $data['start_latitude'] : null;
        if (array_key_exists('start_longitude', $data)) $updateData['start_longitude'] = $data['start_longitude'] !== null ? (float) $data['start_longitude'] : null;
        if (array_key_exists('end_latitude', $data)) $updateData['end_latitude'] = $data['end_latitude'] !== null ? (float) $data['end_latitude'] : null;
        if (array_key_exists('end_longitude', $data)) $updateData['end_longitude'] = $data['end_longitude'] !== null ? (float) $data['end_longitude'] : null;

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            if (!empty($updateData)) {
                $this->model->update($id, $updateData);
            }

            if (isset($data['zone_ids']) && is_array($data['zone_ids'])) {
                $this->model->setZones($id, $data['zone_ids']);
            }

            $db->commit();
            $route = $this->model->findById($id);
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

    public function status(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('route_status', 10, 900);

        if (!hasPermission($payload, 'delivery_route', 'edit')) {
            jsonError('No tenes permiso para cambiar estado', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);
        $newStatus = sanitizeString($data['status'] ?? '', 50);

        if ($id <= 0 || $newStatus === '') jsonError('ID y status son requeridos', 422);

        $existing = $this->model->findById($id);
        if ($existing === null) jsonError('Ruta no encontrada', 404);

        $allowedTransitions = [
            'draft'     => ['active', 'cancelled'],
            'active'    => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => ['draft'],
        ];

        $currentStatus = $existing['status_name'] ?? 'draft';
        $allowedNext = $allowedTransitions[$currentStatus] ?? [];

        if (!in_array($newStatus, $allowedNext, true)) {
            jsonError("No se puede cambiar de '{$currentStatus}' a '{$newStatus}'", 409);
        }

        $statusId = $this->model->getStatusId($newStatus);
        if ($statusId === null) jsonError('Estado invalido', 422);

        $this->model->update($id, ['status_id' => $statusId]);
        $route = $this->model->findById($id);

        jsonSuccess(['route' => $route], 'Estado actualizado');
    }

    public function packages(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'delivery_route', 'read')) {
            jsonError('No tenes permiso', 403);
        }

        $routeId = (int) ($_GET['route_id'] ?? 0);
        if ($routeId <= 0) jsonError('ID de ruta requerido', 422);

        $packages = $this->model->getCandidatePackages($routeId);
        jsonSuccess(['packages' => $packages]);
    }

    public function generateStops(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('route_generate_stops', 10, 900);

        if (!hasPermission($payload, 'delivery_route', 'edit')) {
            jsonError('No tenes permiso', 403);
        }

        $data = getJsonInput();
        $routeId = (int) ($data['route_id'] ?? 0);
        $packageIds = $data['package_ids'] ?? [];

        if ($routeId <= 0 || !is_array($packageIds) || empty($packageIds)) {
            jsonError('route_id y package_ids requeridos', 422);
        }

        $route = $this->model->findById($routeId);
        if ($route === null) jsonError('Ruta no encontrada', 404);

        $db = Database::getConnection();
        $stopModel = new RouteStopModel($db);
        $db->beginTransaction();

        try {
            $stops = $stopModel->generateFromPackages($routeId, $packageIds);
            $this->model->update($routeId, ['optimized_geometry' => null]);
            $db->commit();
            jsonSuccess(['stops' => $stops], count($stops) . ' paradas generadas');
        } catch (\Throwable $e) {
            $db->rollBack();
            apiLog('ERROR', 'Error generating stops: ' . $e->getMessage());
            jsonError('Error al generar paradas', 500);
        }
    }

    public function optimize(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('route_optimize', 10, 900);

        if (!hasPermission($payload, 'delivery_route', 'edit')) {
            jsonError('No tenes permiso', 403);
        }

        $data = getJsonInput();
        $routeId = (int) ($data['route_id'] ?? 0);
        if ($routeId <= 0) jsonError('route_id requerido', 422);

        $route = $this->model->findById($routeId);
        if ($route === null) jsonError('Ruta no encontrada', 404);

        $stops = (new RouteStopModel(Database::getConnection()))->listByRoute($routeId);
        $pendingStops = array_filter($stops, fn($s) => ($s['status_name'] ?? '') === 'pending');

        if (empty($pendingStops)) {
            jsonError('No hay paradas pendientes para optimizar', 422);
        }

        $coordsStops = array_values(array_filter(
            $pendingStops,
            fn($s) => !empty($s['latitude']) && !empty($s['longitude'])
        ));

        if (count($coordsStops) < 2) {
            jsonError('Se necesitan al menos 2 paradas con coordenadas', 422);
        }

        $coordinates = array_map(
            fn($stop) => "{$stop['longitude']},{$stop['latitude']}",
            $coordsStops
        );
        $coordStr = implode(';', $coordinates);
        $osrmUrl = "https://router.project-osrm.org/route/v1/driving/{$coordStr}?overview=full&geometries=geojson&steps=false&alternatives=false";

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $osrmUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_USERAGENT => 'VunoShipping/1.0',
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || $response === false) {
                apiLog('WARN', "OSRM request failed for route {$routeId}: HTTP {$httpCode}");
                jsonSuccess(['message' => 'No se pudo optimizar con OSRM, se usa orden original', 'stops' => $pendingStops, 'optimized_route' => null]);
            }

            $osrmData = json_decode($response, true);
            if (!isset($osrmData['waypoints']) || !is_array($osrmData['waypoints'])) {
                jsonSuccess(['message' => 'OSRM no retorno waypoints, se usa orden original', 'stops' => $pendingStops, 'optimized_route' => null]);
            }

            $orderMap = [];
            foreach ($osrmData['waypoints'] as $i => $wp) {
                $orderMap[$wp['waypoint_index']] = $i;
            }

            $ordered = [];
            foreach ($orderMap as $origIdx => $newPos) {
                if (isset($coordsStops[$origIdx])) {
                    $ordered[$newPos] = $coordsStops[$origIdx];
                }
            }
            ksort($ordered);
            $sortedStops = array_values($ordered);

            foreach ($pendingStops as $stop) {
                if (empty($stop['latitude']) || empty($stop['longitude'])) {
                    $sortedStops[] = $stop;
                }
            }

            $geometry = null;
            if (isset($osrmData['routes'][0]['geometry']['coordinates'])
                && is_array($osrmData['routes'][0]['geometry']['coordinates'])) {
                $geometry = array_map(
                    fn($c) => [(float) $c[1], (float) $c[0]],
                    $osrmData['routes'][0]['geometry']['coordinates']
                );
            }

            $db = Database::getConnection();
            $stopModel = new RouteStopModel($db);
            $db->beginTransaction();

            try {
                $order = 1;
                foreach ($sortedStops as $stop) {
                    $stopModel->update((int) $stop['id'], ['stop_order' => $order]);
                    $order++;
                }
                $this->model->update($routeId, [
                    'optimized_geometry' => $geometry !== null ? json_encode($geometry) : null,
                ]);
                $db->commit();
            } catch (\Throwable $e) {
                $db->rollBack();
                throw $e;
            }

            $finalStops = $stopModel->listByRoute($routeId);
            jsonSuccess(['stops' => $finalStops, 'optimized_route' => $geometry], 'Ruta optimizada');
        } catch (\Throwable $e) {
            apiLog('ERROR', 'Error optimizing route: ' . $e->getMessage());
            jsonError('Error al optimizar la ruta', 500);
        }
    }

    public function driverRoutes(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'delivery_route', 'read')) {
            jsonError('No tenes permiso', 403);
        }

        $userId = (int) ($payload['sub'] ?? 0);
        if ($userId <= 0) jsonError('Usuario no valido', 401);

        $routes = $this->model->getRoutesByDriver($userId);
        jsonSuccess(['routes' => $routes]);
    }
}
