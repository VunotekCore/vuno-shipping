<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\DriverLocationModel;
use App\Models\RouteModel;
use App\Config\Database;

class DriverLocationController
{
    private DriverLocationModel $model;

    public function __construct()
    {
        $this->model = new DriverLocationModel(Database::getConnection());
    }

    public function post(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('driver_location', 60, 60);

        if (!hasPermission($payload, 'delivery_route', 'read')) {
            jsonError('No tenes permiso', 403);
        }

        $data   = getJsonInput();
        $userId = (int) ($payload['sub'] ?? 0);
        if ($userId <= 0) jsonError('Usuario no valido', 401);

        $this->model->upsert($userId, $data);

        $routeModel = new RouteModel(Database::getConnection());
        $activeRoute = $routeModel->getActiveRouteByDriver($userId);

        $this->model->logHistory(
            $userId,
            $activeRoute['vehicle_id'] ?? null,
            $activeRoute['id'] ?? null,
            (float) ($data['latitude'] ?? 0),
            (float) ($data['longitude'] ?? 0),
            isset($data['accuracy']) ? (float) $data['accuracy'] : null,
            isset($data['speed']) ? (float) $data['speed'] : null,
            isset($data['heading']) ? (float) $data['heading'] : null,
            isset($data['battery_level']) ? (float) $data['battery_level'] : null,
        );

        jsonSuccess(null, 'Ubicacion actualizada');
    }

    public function get(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'delivery_route', 'read')) {
            jsonError('No tenes permiso', 403);
        }

        $requestedUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;
        $authUserId = (int) ($payload['sub'] ?? 0);

        if ($requestedUserId !== null) {
            $location = $this->model->getByUser($requestedUserId);
            jsonSuccess(['location' => $location]);
        } elseif ($authUserId > 0) {
            $location = $this->model->getByUser($authUserId);
            jsonSuccess(['location' => $location]);
        } else {
            $locations = $this->model->getAllActive();
            jsonSuccess(['locations' => $locations]);
        }
    }

    public function history(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'delivery_route', 'read')) {
            jsonError('No tenes permiso', 403);
        }

        $userId = (int) ($_GET['user_id'] ?? 0);
        $from   = $_GET['from'] ?? date('Y-m-d 00:00:00');
        $to     = $_GET['to'] ?? date('Y-m-d H:i:s');

        if ($userId <= 0) jsonError('user_id requerido', 422);

        $history = $this->model->getHistory($userId, $from, $to);
        jsonSuccess(['history' => $history]);
    }
}
