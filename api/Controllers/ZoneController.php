<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\ZoneModel;
use App\Config\Database;

class ZoneController
{
    private ZoneModel $model;

    public function __construct()
    {
        $this->model = new ZoneModel(Database::getConnection());
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'zone', 'read')) {
            jsonError('No tenés permiso para ver zonas', 403);
        }

        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->model->count();
        $zones = $this->model->listPaginated($limit, $offset);

        jsonSuccess([
            'zones' => $zones,
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

        if (!hasPermission($payload, 'zone', 'read')) {
            jsonError('No tenés permiso para ver zonas', 403);
        }

        $zones = $this->model->listAll();
        jsonSuccess(['zones' => $zones]);
    }

    public function create(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('zone_create', 10, 900);

        if (!hasPermission($payload, 'zone', 'create')) {
            jsonError('No tenés permiso para crear zonas', 403);
        }

        $data = getJsonInput();

        $name = $data['name'] ?? '';
        if ($name === '') {
            jsonError('El nombre es requerido', 422);
        }

        $insertData = ['name' => sanitizeString($name, 100)];

        if (isset($data['description'])) {
            $insertData['description'] = sanitizeString($data['description'], 500);
        }
        if (isset($data['lat'])) {
            $lat = (float) $data['lat'];
            if ($lat < -90 || $lat > 90) {
                jsonError('La latitud debe estar entre -90 y 90', 422);
            }
            $insertData['lat'] = $lat;
        }
        if (isset($data['lng'])) {
            $lng = (float) $data['lng'];
            if ($lng < -180 || $lng > 180) {
                jsonError('La longitud debe estar entre -180 y 180', 422);
            }
            $insertData['lng'] = $lng;
        }
        if (array_key_exists('surcharge', $data)) {
            $insertData['surcharge'] = max(0, (float) $data['surcharge']);
        }

        $id = $this->model->create($insertData);
        $zone = $this->model->findById($id);

        jsonSuccess(['zone' => $zone], 'Zona creada', 201);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('zone_update', 20, 900);

        if (!hasPermission($payload, 'zone', 'edit')) {
            jsonError('No tenés permiso para editar zonas', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            jsonError('ID de zona inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Zona no encontrada', 404);
        }

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = sanitizeString($data['name'], 100);
        }
        if (array_key_exists('description', $data)) {
            $updateData['description'] = $data['description'] !== null ? sanitizeString($data['description'], 500) : null;
        }
        if (array_key_exists('lat', $data)) {
            if ($data['lat'] !== null) {
                $lat = (float) $data['lat'];
                if ($lat < -90 || $lat > 90) {
                    jsonError('La latitud debe estar entre -90 y 90', 422);
                }
                $updateData['lat'] = $lat;
            } else {
                $updateData['lat'] = null;
            }
        }
        if (array_key_exists('lng', $data)) {
            if ($data['lng'] !== null) {
                $lng = (float) $data['lng'];
                if ($lng < -180 || $lng > 180) {
                    jsonError('La longitud debe estar entre -180 y 180', 422);
                }
                $updateData['lng'] = $lng;
            } else {
                $updateData['lng'] = null;
            }
        }
        if (array_key_exists('is_active', $data)) {
            $updateData['is_active'] = (int) $data['is_active'];
        }
        if (array_key_exists('surcharge', $data)) {
            $updateData['surcharge'] = max(0, (float) $data['surcharge']);
        }

        if (empty($updateData)) {
            jsonError('No hay datos para actualizar', 422);
        }

        $this->model->update($id, $updateData);
        $updated = $this->model->findById($id);

        jsonSuccess(['zone' => $updated], 'Zona actualizada');
    }
}
