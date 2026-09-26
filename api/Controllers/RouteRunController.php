<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\RouteRunModel;
use App\Models\RouteModel;
use App\Models\RouteStopModel;
use App\Config\Database;
use App\Services\PushService;
use App\Models\PushSubscriptionModel;

class RouteRunController
{
    private RouteRunModel $model;

    public function __construct()
    {
        $this->model = new RouteRunModel(Database::getConnection());
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'delivery_route', 'read')) {
            jsonError('No tenes permiso para ver despachos', 403);
        }

        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $limit    = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
        $offset   = ($page - 1) * $limit;
        $status   = $_GET['status'] ?? null;
        $driverId = isset($_GET['driver_id']) ? (int) $_GET['driver_id'] : null;
        $routeId  = isset($_GET['route_id']) ? (int) $_GET['route_id'] : null;
        $date     = $_GET['date'] ?? null;

        $total = $this->model->count($status, $driverId, $routeId, $date);
        $runs  = $this->model->listPaginated($limit, $offset, $status, $driverId, $routeId, $date);

        jsonSuccess([
            'runs' => $runs,
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
            jsonError('No tenes permiso para ver despachos', 403);
        }

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) jsonError('ID de despacho invalido', 422);

        $run = $this->model->findById($id);
        if ($run === null) jsonError('Despacho no encontrado', 404);

        $stops = (new RouteStopModel(Database::getConnection()))->listByRun($id);
        $run['stops'] = $stops;

        $run['zones'] = (new RouteModel(Database::getConnection()))->getZones((int) $run['route_id']);

        jsonSuccess(['run' => $run]);
    }

    public function create(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('route_run_create', 10, 900);

        if (!hasPermission($payload, 'delivery_route', 'create')) {
            jsonError('No tenes permiso para crear despachos', 403);
        }

        $data = getJsonInput();
        $routeId = (int) ($data['route_id'] ?? 0);
        $runDate = $data['run_date'] ?? date('Y-m-d');

        if ($routeId <= 0) jsonError('route_id requerido', 422);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $runDate)) jsonError('run_date invalido', 422);

        $routeModel = new RouteModel(Database::getConnection());
        $route = $routeModel->findById($routeId);
        if ($route === null) jsonError('Ruta maestra no encontrada', 404);

        $conflict = $this->model->hasOpenRunForDate($routeId, $runDate);
        if ($conflict !== null) {
            jsonError("Ya existe un despacho abierto para esta ruta en la fecha (id {$conflict['id']})", 409);
        }

        $assignedTo = isset($data['assigned_to']) ? (int) $data['assigned_to'] : (isset($route['default_driver_id']) ? (int) $route['default_driver_id'] : null);
        $vehicleId  = isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : (isset($route['default_vehicle_id']) ? (int) $route['default_vehicle_id'] : null);
        if ($assignedTo !== null || $vehicleId !== null) {
            $this->validateAssignments($assignedTo, $vehicleId);
        }

        $draftId = $this->model->getStatusId('draft');
        if ($draftId === null) jsonError('Estado draft no configurado', 500);

        $db = Database::getConnection();
        $stopModel = new RouteStopModel($db);
        $db->beginTransaction();

        try {
            $runId = $this->model->create([
                'route_id' => $routeId,
                'run_date' => $runDate,
                'assigned_to' => $assignedTo,
                'vehicle_id' => $vehicleId,
                'status_id' => $draftId,
                'notes' => isset($data['notes']) ? sanitizeString((string) $data['notes'], 1000) : null,
            ]);

            $carriedOver = ['created' => [], 'stops' => []];

            $previous = $this->model->getLastRunForRoute($routeId, $runId);
            if ($previous !== null && (int) $previous['id'] !== $runId) {
                $carriedOver = $stopModel->carryForward($runId, (int) $previous['id']);
            }

            $db->commit();

            $run = $this->model->findById($runId);
            $run['stops'] = $carriedOver['stops'];
            $run['zones'] = $routeModel->getZones($routeId);
            $run['carried_over'] = count($carriedOver['created']);

            if ($assignedTo !== null) {
                $this->notifyDriver(
                    $assignedTo,
                    'Nuevo despacho asignado',
                    ($run['route_name'] ?? 'Ruta') . ' — ' . $runDate,
                    '/backoffice/conductor',
                    'run-assigned'
                );
            }

            jsonSuccess(['run' => $run, 'carried_over' => count($carriedOver['created'])], count($carriedOver['created']) . ' paquetes arrastrados', 201);
        } catch (\Throwable $e) {
            $db->rollBack();
            apiLog('ERROR', 'Error creating route run: ' . $e->getMessage());
            jsonError('Error al crear el despacho', 500);
        }
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('route_run_update', 20, 900);

        if (!hasPermission($payload, 'delivery_route', 'edit')) {
            jsonError('No tenes permiso para editar despachos', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);
        if ($id <= 0) jsonError('ID de despacho invalido', 422);

        $existing = $this->model->findById($id);
        if ($existing === null) jsonError('Despacho no encontrado', 404);

        $updateData = [];

        if (array_key_exists('assigned_to', $data)) $updateData['assigned_to'] = $data['assigned_to'] !== null ? (int) $data['assigned_to'] : null;
        if (array_key_exists('vehicle_id', $data)) $updateData['vehicle_id'] = $data['vehicle_id'] !== null ? (int) $data['vehicle_id'] : null;
        if (array_key_exists('start_time', $data)) $updateData['start_time'] = $data['start_time'] ?: null;
        if (array_key_exists('end_time', $data)) $updateData['end_time'] = $data['end_time'] ?: null;
        if (array_key_exists('notes', $data)) $updateData['notes'] = $data['notes'] !== null ? sanitizeString((string) $data['notes'], 1000) : null;
        if (array_key_exists('run_date', $data)) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $data['run_date'])) jsonError('run_date invalido', 422);
            $updateData['run_date'] = $data['run_date'];
        }

        if (array_key_exists('assigned_to', $updateData) || array_key_exists('vehicle_id', $updateData)) {
            $this->validateAssignments(
                $updateData['assigned_to'] ?? (isset($existing['assigned_to']) ? (int) $existing['assigned_to'] : null),
                $updateData['vehicle_id'] ?? (isset($existing['vehicle_id']) ? (int) $existing['vehicle_id'] : null),
                $id
            );
        }

        $this->model->update($id, $updateData);
        $run = $this->model->findById($id);

        if (array_key_exists('assigned_to', $updateData)) {
            $newDriver = $updateData['assigned_to'] !== null ? (int) $updateData['assigned_to'] : null;
            $oldDriver = isset($existing['assigned_to']) ? (int) $existing['assigned_to'] : null;
            if ($newDriver !== null && $newDriver !== $oldDriver) {
                $this->notifyDriver(
                    $newDriver,
                    'Despacho asignado a vos',
                    ($run['route_name'] ?? 'Ruta') . ' — ' . ($run['run_date'] ?? ''),
                    '/backoffice/conductor',
                    'run-assigned'
                );
            }
        }

        jsonSuccess(['run' => $run], 'Despacho actualizado');
    }

    public function delete(): never
    {
        requireMethod('DELETE');
        $payload = requireAuthMiddleware(true);
        rateLimit('route_run_delete', 5, 900);

        if (!hasPermission($payload, 'delivery_route', 'delete')) {
            jsonError('No tenes permiso para eliminar despachos', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);
        if ($id <= 0) jsonError('ID de despacho invalido', 422);

        $existing = $this->model->findById($id);
        if ($existing === null) jsonError('Despacho no encontrado', 404);

        $this->model->delete($id);
        jsonSuccess(null, 'Despacho eliminado');
    }

    public function status(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('route_run_status', 10, 900);

        if (!hasPermission($payload, 'delivery_route', 'edit')) {
            jsonError('No tenes permiso para cambiar estado', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);
        $newStatus = sanitizeString($data['status'] ?? '', 50);

        if ($id <= 0 || $newStatus === '') jsonError('ID y status son requeridos', 422);

        $existing = $this->model->findById($id);
        if ($existing === null) jsonError('Despacho no encontrado', 404);

        $roleSlug = (string) ($payload['role_slug'] ?? '');
        if ($roleSlug === 'conductor') {
            // El conductor asignado solo puede iniciar su jornada (draft → active)
            // o terminarla (active → completed) cuando no quedan paradas pendientes.
            if (!in_array($newStatus, ['active', 'completed'], true)) {
                jsonError('El conductor solo puede iniciar o finalizar su jornada (draft → active / active → completed)', 403);
            }
            if ((int) ($existing['assigned_to'] ?? 0) !== (int) ($payload['sub'] ?? 0)) {
                jsonError('Este despacho no esta asignado a tu cuenta', 403);
            }
            if ($newStatus === 'active' && ($existing['status_name'] ?? '') !== 'draft') {
                jsonError('El despacho ya no esta en estado draft', 409);
            }
            if ($newStatus === 'completed' && ($existing['status_name'] ?? '') !== 'active') {
                jsonError('El despacho debe estar en curso (active) para terminar la jornada', 409);
            }
        } elseif ($roleSlug !== 'admin') {
            jsonError('Solo el administrador puede cambiar el estado del despacho', 403);
        }

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

        if ($newStatus === 'completed' && (int) ($existing['pending_stops'] ?? 0) > 0) {
            jsonError('No se puede completar el despacho con paradas pendientes', 409);
        }

        $statusId = $this->model->getStatusId($newStatus);
        if ($statusId === null) jsonError('Estado invalido', 422);

        $updateData = ['status_id' => $statusId];

        if ($newStatus === 'active' && empty($existing['start_time'])) {
            $updateData['start_time'] = date('H:i:s');
        }
        if ($newStatus === 'completed' && empty($existing['end_time'])) {
            $updateData['end_time'] = date('H:i:s');
        }

        $this->model->update($id, $updateData);
        $run = $this->model->findById($id);

        $driverId = isset($run['assigned_to']) ? (int) $run['assigned_to'] : null;
        if ($driverId !== null && $driverId > 0) {
            $statusMsg = match ($newStatus) {
                'active' => 'Jornada iniciada',
                'completed' => 'Jornada terminada',
                'cancelled' => 'Despacho cancelado',
                'draft' => 'Despacho reactivado',
                default => 'Estado actualizado',
            };
            $this->notifyDriver(
                $driverId,
                $statusMsg,
                ($run['route_name'] ?? 'Ruta') . ' — ' . ($run['run_date'] ?? ''),
                '/backoffice/conductor',
                'run-status'
            );
        }

        jsonSuccess(['run' => $run], 'Estado actualizado');
    }

    public function packages(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'delivery_route', 'read')) {
            jsonError('No tenes permiso', 403);
        }

        $runId = (int) ($_GET['run_id'] ?? 0);
        if ($runId <= 0) jsonError('run_id requerido', 422);

        $run = $this->model->findById($runId);
        if ($run === null) jsonError('Despacho no encontrado', 404);

        $packages = $this->model->getCandidatePackages((int) $run['route_id'], $runId);
        jsonSuccess(['packages' => $packages]);
    }

    public function generateStops(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('route_run_generate_stops', 10, 900);

        if (!hasPermission($payload, 'delivery_route', 'edit')) {
            jsonError('No tenes permiso', 403);
        }

        $data = getJsonInput();
        $runId = (int) ($data['run_id'] ?? 0);
        $packageIds = $data['package_ids'] ?? [];

        if ($runId <= 0 || !is_array($packageIds) || empty($packageIds)) {
            jsonError('run_id y package_ids requeridos', 422);
        }

        $run = $this->model->findById($runId);
        if ($run === null) jsonError('Despacho no encontrado', 404);

        if ($run['status_name'] !== 'draft') {
            jsonError('Solo se pueden agregar paradas a un despacho en estado draft', 409);
        }

        $db = Database::getConnection();
        $stopModel = new RouteStopModel($db);
        $db->beginTransaction();

        try {
            $result = $stopModel->generateFromPackages($runId, $packageIds);
            $this->applyCorridorOrder($run, $stopModel);

            $packageModel = new \App\Models\PackageModel($db);
            $outForDeliveryStatusId = $packageModel->getStatusId('out_for_delivery');
            $trackingModel = new \App\Models\TrackingEventModel($db);
            $notifiedPackages = [];

            foreach ($result['created'] as $stop) {
                $packageId = (int) $stop['package_id'];
                $pkg = $packageModel->findById($packageId);
                $currentStatus = $packageModel->getStatusName((int) ($pkg['status_id'] ?? 0));
                if ($currentStatus === 'in_nicaragua' && $outForDeliveryStatusId !== null) {
                    $packageModel->update($packageId, ['status_id' => $outForDeliveryStatusId]);
                    $trackingModel->create([
                        'package_id' => $packageId,
                        'status' => 'out_for_delivery',
                        'description' => 'Asignado al despacho ' . ($run['route_name'] ?? 'local') . ' — en ruta de entrega local',
                        'recorded_at' => date('Y-m-d H:i:s'),
                        'created_by' => (int) $payload['sub'],
                    ]);
                    $notifiedPackages[] = $pkg;
                }
            }

            $this->model->update($runId, ['optimized_geometry' => null]);
            $db->commit();

            $notifier = new \App\Services\PackageNotifierService($db);
            $descOut = 'Asignado al despacho ' . ($run['route_name'] ?? 'local') . ' — en ruta de entrega local';
            foreach ($notifiedPackages as $pkg) {
                $notifier->notifyStatusChange($pkg, 'in_nicaragua', 'out_for_delivery', $descOut);
            }

            $driverId = isset($run['assigned_to']) ? (int) $run['assigned_to'] : null;
            if ($driverId !== null && $driverId > 0) {
                $this->notifyDriver(
                    $driverId,
                    'Paradas asignadas a tu ruta',
                    'Se generaron ' . count($result['created']) . ' paradas para ' . ($run['route_name'] ?? 'tu ruta'),
                    '/backoffice/conductor',
                    'run-stops'
                );
            }

            $updated = $stopModel->listByRun($runId);
            jsonSuccess(['stops' => $updated, 'created' => $result['created']], count($result['created']) . ' paradas generadas');
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
        rateLimit('route_run_optimize', 10, 900);

        if (!hasPermission($payload, 'delivery_route', 'edit')) {
            jsonError('No tenes permiso', 403);
        }

        $data = getJsonInput();
        $runId = (int) ($data['run_id'] ?? 0);
        if ($runId <= 0) jsonError('run_id requerido', 422);

        $run = $this->model->findById($runId);
        if ($run === null) jsonError('Despacho no encontrado', 404);

        $stopModel = new RouteStopModel(Database::getConnection());
        $stops = $stopModel->listByRun($runId);
        $pendingStops = array_values(array_filter($stops, fn($s) => ($s['status_name'] ?? '') === 'pending'));

        if (empty($pendingStops)) {
            jsonError('No hay paradas pendientes para optimizar', 422);
        }

        $coordsStops = array_values(array_filter($pendingStops, fn($s) => !empty($s['latitude']) && !empty($s['longitude'])));
        if (count($coordsStops) < 2) {
            jsonError('Se necesitan al menos 2 paradas con coordenadas', 422);
        }

        $routeModel = new RouteModel(Database::getConnection());
        $zones = $routeModel->getZones((int) $run['route_id']);
        $zonePositions = [];
        foreach ($zones as $i => $zone) {
            $zonePositions[(int) $zone['id']] = $i;
        }
        $corridor = $this->buildCorridor($run, $zones);

        $orderedBase = $this->orderStopsByCorridor($pendingStops, $corridor, $zonePositions);

        $withCoords = array_values(array_filter($orderedBase, fn($s) => !empty($s['latitude']) && !empty($s['longitude'])));
        $noCoords = array_values(array_filter($orderedBase, fn($s) => empty($s['latitude']) || empty($s['longitude'])));
        $maxBlocks = count($withCoords) > 150 ? 0 : 8;

        $orderedStops = array_merge($this->splitByZoneAndTrip($withCoords, $maxBlocks), $noCoords);

        $geometry = null;
        $withLocs = array_values(array_filter($orderedStops, fn($s) => !empty($s['latitude']) && !empty($s['longitude'])));
        if (count($withLocs) >= 2) {
            $coords = array_map(fn($s) => "{$s['longitude']},{$s['latitude']}", $withLocs);
            $routeData = $this->osrmJson('https://router.project-osrm.org/route/v1/driving/' . implode(';', $coords) . '?overview=full&geometries=geojson&steps=false&alternatives=false');
            if ($routeData !== null && isset($routeData['routes'][0]['geometry']['coordinates']) && is_array($routeData['routes'][0]['geometry']['coordinates'])) {
                $geometry = array_map(
                    fn($c) => [(float) $c[1], (float) $c[0]],
                    $routeData['routes'][0]['geometry']['coordinates']
                );
            }
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $order = 1;
            foreach ($orderedStops as $stop) {
                $stopModel->update((int) $stop['id'], ['stop_order' => $order]);
                $order++;
            }
            $this->model->update($runId, [
                'optimized_geometry' => $geometry !== null ? json_encode($geometry) : null,
            ]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            apiLog('ERROR', 'Error persisting optimized order for run ' . $runId . ': ' . $e->getMessage());
            jsonError('Error al optimizar el despacho', 500);
        }

        $finalStops = $stopModel->listByRun($runId);
        jsonSuccess(['stops' => $finalStops, 'optimized_route' => $geometry], 'Despacho optimizado');
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

        $runs = $this->model->getRoutesByDriver($userId);
        jsonSuccess(['runs' => $runs]);
    }

    private function validateAssignments(?int $assignedTo, ?int $vehicleId, ?int $excludeRunId = null): void
    {
        if ($assignedTo !== null) {
            $conflict = $this->model->hasOpenAssignment('assigned_to', $assignedTo, $excludeRunId);
            if ($conflict !== null) {
                jsonError("El conductor ya tiene el despacho '{$conflict['name']}' en curso", 409);
            }
        }

        if ($vehicleId !== null) {
            $conflict = $this->model->hasOpenAssignment('vehicle_id', $vehicleId, $excludeRunId);
            if ($conflict !== null) {
                jsonError("El vehículo ya está asignado al despacho '{$conflict['name']}' en curso", 409);
            }
        }
    }

    private function buildCorridor(array $run, array $zones): array
    {
        $corridor = [];
        $traj = $run['trajectory'] ?? null;

        if (!empty($traj)) {
            $points = is_string($traj) ? json_decode($traj, true) : $traj;
            if (is_array($points)) {
                foreach ($points as $p) {
                    if (is_array($p) && isset($p[0], $p[1]) && is_numeric($p[0]) && is_numeric($p[1])) {
                        $corridor[] = [(float) $p[1], (float) $p[0]];
                    }
                }
            }
        }

        if (count($corridor) < 2) {
            $sl = $run['start_latitude'] ?? null;
            $sln = $run['start_longitude'] ?? null;
            $el = $run['end_latitude'] ?? null;
            $eln = $run['end_longitude'] ?? null;
            if ($sl !== null && $sln !== null && $el !== null && $eln !== null) {
                $corridor = [[(float) $sl, (float) $sln], [(float) $el, (float) $eln]];
            }
        }

        if (count($corridor) < 2) {
            foreach ($zones as $zone) {
                if (isset($zone['lat'], $zone['lng']) && $zone['lat'] !== null && $zone['lng'] !== null) {
                    $corridor[] = [(float) $zone['lat'], (float) $zone['lng']];
                }
            }
        }

        return count($corridor) >= 2 ? $corridor : [];
    }

    private function orderStopsByCorridor(array $stops, array $corridor, array $zonePositions): array
    {
        $withCoords = [];
        $noCoords = [];

        foreach ($stops as $stop) {
            $lat = $stop['latitude'] ?? null;
            $lng = $stop['longitude'] ?? null;
            if ($lat !== null && $lng !== null && $lat !== '' && $lng !== '') {
                $withCoords[] = $stop;
            } else {
                $noCoords[] = $stop;
            }
        }

        $hasCorridor = !empty($corridor);

        foreach ($withCoords as $idx => &$stop) {
            $zoneId = isset($stop['zone_id']) ? (int) $stop['zone_id'] : 0;
            $stop['_zone_key'] = $zoneId > 0 && isset($zonePositions[$zoneId]) ? $zonePositions[$zoneId] : PHP_INT_MAX;
            $stop['_along'] = $hasCorridor
                ? $this->alongCorridor([(float) $stop['latitude'], (float) $stop['longitude']], $corridor)
                : null;
            $stop['_seq'] = $idx;
        }
        unset($stop);

        usort($withCoords, function ($a, $b) use ($hasCorridor) {
            if ($a['_zone_key'] !== $b['_zone_key']) {
                return $a['_zone_key'] <=> $b['_zone_key'];
            }
            if ($hasCorridor && $a['_along'] !== null && $b['_along'] !== null && $a['_along'] !== $b['_along']) {
                return $a['_along'] <=> $b['_along'];
            }
            return $a['_seq'] <=> $b['_seq'];
        });

        foreach ($withCoords as &$stop) {
            unset($stop['_zone_key'], $stop['_along'], $stop['_seq']);
        }
        unset($stop);

        return array_merge($withCoords, $noCoords);
    }

    private function splitByZoneAndTrip(array $stops, int $maxBlocks): array
    {
        $result = [];
        $block = [];
        $currentZone = null;
        $blocksProcessed = 0;

        foreach ($stops as $stop) {
            $zone = isset($stop['zone_id']) ? (int) $stop['zone_id'] : 0;
            if ($currentZone === null || $zone === $currentZone) {
                $block[] = $stop;
                if ($currentZone === null) $currentZone = $zone;
            } else {
                $result = array_merge($result, $blocksProcessed < $maxBlocks ? $this->optimizeZoneBlock($block) : $block);
                $blocksProcessed++;
                $block = [$stop];
                $currentZone = $zone;
            }
        }

        if (!empty($block)) {
            $result = array_merge($result, $blocksProcessed < $maxBlocks ? $this->optimizeZoneBlock($block) : $block);
        }

        return $result;
    }

    private function optimizeZoneBlock(array $stops): array
    {
        if (count($stops) < 2 || count($stops) > 60) return $stops;

        $coords = array_map(fn($s) => "{$s['longitude']},{$s['latitude']}", $stops);
        $data = $this->osrmJson('https://router.project-osrm.org/trip/v1/driving/' . implode(';', $coords) . '?roundtrip=false&source=any&destination=any');

        if ($data === null || !isset($data['waypoints']) || !is_array($data['waypoints'])) {
            return $stops;
        }

        $reordered = [];
        foreach ($data['waypoints'] as $wp) {
            $idx = (int) ($wp['waypoint_index'] ?? -1);
            if (isset($stops[$idx])) {
                $reordered[] = $stops[$idx];
            }
        }

        return !empty($reordered) ? $reordered : $stops;
    }

    private function osrmJson(string $url): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_USERAGENT => 'VunoShipping/1.0',
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            return null;
        }

        $data = json_decode($response, true);
        return is_array($data) ? $data : null;
    }

    private function applyCorridorOrder(array $run, RouteStopModel $stopModel): void
    {
        $routeModel = new RouteModel(Database::getConnection());
        $zones = $routeModel->getZones((int) $run['route_id']);
        $zonePositions = [];
        foreach ($zones as $i => $zone) {
            $zonePositions[(int) $zone['id']] = $i;
        }
        $corridor = $this->buildCorridor($run, $zones);

        $stops = $stopModel->listByRun((int) $run['id']);
        $pending = array_values(array_filter($stops, fn($s) => ($s['status_name'] ?? '') === 'pending'));
        $ordered = $this->orderStopsByCorridor($pending, $corridor, $zonePositions);

        $order = 1;
        foreach ($ordered as $stop) {
            $stopModel->update((int) $stop['id'], ['stop_order' => $order]);
            $order++;
        }
    }

    private function haversine(array $a, array $b): float
    {
        $r = 6371000.0;
        $dLat = deg2rad($b[0] - $a[0]);
        $dLng = deg2rad($b[1] - $a[1]);
        $s = sin($dLat / 2) ** 2
            + cos(deg2rad($a[0])) * cos(deg2rad($b[0])) * sin($dLng / 2) ** 2;
        return 2 * $r * asin(min(1.0, sqrt($s)));
    }

    private function projectPointOnSegment(array $p, array $a, array $b): float
    {
        $cos = cos(deg2rad(($a[0] + $b[0]) / 2)) ?: 1e-9;
        $abx = ($b[1] - $a[1]) * $cos;
        $aby = $b[0] - $a[0];
        $apx = ($p[1] - $a[1]) * $cos;
        $apy = $p[0] - $a[0];
        $len2 = ($abx ** 2) + ($aby ** 2);

        if ($len2 <= 0) return 0.0;

        $t = (($apx * $abx) + ($apy * $aby)) / $len2;
        return max(0.0, min(1.0, $t));
    }

    private function closestPointOnSegment(array $p, array $a, array $b, float $t): array
    {
        if ($t <= 0) return $a;
        if ($t >= 1) return $b;
        $cos = cos(deg2rad(($a[0] + $b[0]) / 2)) ?: 1e-9;
        $abx = ($b[1] - $a[1]) * $cos;
        $aby = $b[0] - $a[0];
        return [$a[0] + $t * $aby, ($a[1] + $t * $abx) / $cos];
    }

    private function alongCorridor(array $point, array $corridor): float
    {
        $cum = 0.0;
        $best = null;
        $total = count($corridor);

        for ($i = 0; $i < $total - 1; $i++) {
            $a = $corridor[$i];
            $b = $corridor[$i + 1];
            $segLen = $this->haversine($a, $b);
            $t = $this->projectPointOnSegment($point, $a, $b);
            $dist = $this->haversine($point, $this->closestPointOnSegment($point, $a, $b, $t));
            if ($best === null || $dist < $best[1]) {
                $best = [$cum + $segLen * $t, $dist];
            }
            $cum += $segLen;
        }

        return $best !== null ? $best[0] : 0.0;
    }

    private function notifyDriver(int $driverId, string $title, string $body, string $url, string $tag): void
    {
        try {
            $push = new PushService(new PushSubscriptionModel(Database::getConnection()));
            $push->sendToUser($driverId, $title, $body, $url, $tag);
        } catch (\Throwable $e) {
            apiLog('ERROR', 'Route run push failed: ' . $e->getMessage());
        }
    }
}
