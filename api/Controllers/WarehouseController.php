<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\WarehouseModel;
use App\Config\Database;

class WarehouseController
{
    private WarehouseModel $model;

    public function __construct()
    {
        $this->model = new WarehouseModel(Database::getConnection());
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();
        if (!hasPermission($payload, 'warehouse', 'read')) {
            jsonError('No tenés permiso', 403);
        }

        if (isset($_GET['all'])) {
            jsonSuccess(['warehouses' => $this->model->listAll()]);
        }

        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->model->count();
        $items = $this->model->listPaginated($limit, $offset);

        jsonSuccess([
            'warehouses' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    public function create(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('warehouse_create', 10, 900);
        if (!hasPermission($payload, 'warehouse', 'create')) {
            jsonError('No tenés permiso', 403);
        }

        $data = getJsonInput();
        $name = sanitizeString($data['name'] ?? '', 100);
        $code = strtoupper(sanitizeString($data['code'] ?? '', 20));

        if ($name === '' || $code === '') {
            jsonError('Nombre y código son requeridos', 422);
        }

        $origin = sanitizeString($data['origin'] ?? 'online', 20);
        if (!in_array($origin, ['online', 'local'], true)) {
            $origin = 'online';
        }

        if ($this->model->findByCode($code) !== null) {
            jsonError('Ya existe una bodega con ese código', 409);
        }

        $id = $this->model->create([
            'name' => $name,
            'code' => $code,
            'origin' => $origin,
            'address' => sanitizeString($data['address'] ?? '', 255) ?: null,
            'is_active' => isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1,
        ]);

        jsonSuccess(['warehouse' => $this->model->findById($id)], 'Bodega creada', 201);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('warehouse_update', 20, 900);
        if (!hasPermission($payload, 'warehouse', 'edit')) {
            jsonError('No tenés permiso', 403);
        }

        $data = getJsonInput();
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            jsonError('ID inválido', 422);
        }

        $existing = $this->model->findById($id);
        if (!$existing) {
            jsonError('Bodega no encontrada', 404);
        }

        $update = [];

        if (array_key_exists('name', $data)) {
            $name = sanitizeString((string) $data['name'], 100);
            if ($name === '') {
                jsonError('El nombre es requerido', 422);
            }
            $update['name'] = $name;
        }
        if (array_key_exists('code', $data)) {
            $code = strtoupper(sanitizeString((string) $data['code'], 20));
            if ($code === '' || $this->model->existsCode($code, $id)) {
                jsonError('Código inválido o ya en uso', 422);
            }
            $update['code'] = $code;
        }
        if (array_key_exists('origin', $data)) {
            $origin = sanitizeString((string) $data['origin'], 20);
            $update['origin'] = in_array($origin, ['online', 'local'], true) ? $origin : $existing['origin'];
        }
        if (array_key_exists('address', $data)) {
            $update['address'] = sanitizeString((string) $data['address'], 255) ?: null;
        }
        if (array_key_exists('is_active', $data)) {
            $update['is_active'] = !empty($data['is_active']) ? 1 : 0;
        }

        if (!empty($update)) {
            $this->model->update($id, $update);
        }

        jsonSuccess(['warehouse' => $this->model->findById($id)], 'Bodega actualizada');
    }

    public function delete(): never
    {
        requireMethod('DELETE');
        $payload = requireAuthMiddleware(true);
        rateLimit('warehouse_delete', 5, 900);
        if (!hasPermission($payload, 'warehouse', 'delete')) {
            jsonError('No tenés permiso', 403);
        }

        $data = getJsonInput();
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            jsonError('ID inválido', 422);
        }

        if ($this->model->countLocations($id) > 0) {
            jsonError('No se puede eliminar: la bodega tiene ubicaciones', 409);
        }

        $this->model->delete($id);
        jsonSuccess(['message' => 'Bodega eliminada']);
    }
}
