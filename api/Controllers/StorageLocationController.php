<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\StorageLocationModel;
use App\Models\WarehouseModel;
use App\Services\PackageStorageService;
use App\Config\Database;

class StorageLocationController
{
    private StorageLocationModel $model;

    public function __construct()
    {
        $this->model = new StorageLocationModel(Database::getConnection());
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();
        if (!hasPermission($payload, 'storage_location', 'read')) {
            jsonError('No tenés permiso', 403);
        }

        $warehouseId = isset($_GET['warehouse_id']) ? (int) $_GET['warehouse_id'] : null;
        if ($warehouseId !== null && $warehouseId <= 0) {
            $warehouseId = null;
        }

        if (isset($_GET['all'])) {
            jsonSuccess(['storage_locations' => $this->model->listAll($warehouseId)]);
        }

        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->model->count($warehouseId);
        $items = $this->model->listPaginated($limit, $offset, $warehouseId);

        jsonSuccess([
            'storage_locations' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    /**
     * Ubicaciones disponibles de una bodega + sugerencia automatica segun
     * clasificacion. Usado por el modal de almacenaje / despacho.
     */
    public function available(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();
        if (!hasPermission($payload, 'storage_location', 'read')) {
            jsonError('No tenés permiso', 403);
        }

        $warehouseId = (int) ($_GET['warehouse_id'] ?? 0);
        if ($warehouseId <= 0) {
            jsonError('warehouse_id es requerido', 422);
        }

        $categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int) $_GET['category_id'] : null;

        $service = new PackageStorageService(Database::getConnection());
        $locations = (new StorageLocationModel(Database::getConnection()))->findByWarehouse($warehouseId, $categoryId);
        $suggested = $service->autoSuggest($warehouseId, $categoryId);

        jsonSuccess([
            'locations' => $locations,
            'suggested_location_id' => $suggested ? (int) $suggested['id'] : null,
        ]);
    }

    public function create(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('storage_location_create', 10, 900);
        if (!hasPermission($payload, 'storage_location', 'create')) {
            jsonError('No tenés permiso', 403);
        }

        $data = getJsonInput();
        $warehouseId = (int) ($data['warehouse_id'] ?? 0);
        if ($warehouseId <= 0 || !(new WarehouseModel(Database::getConnection()))->findById($warehouseId)) {
            jsonError('Bodega inválida', 422);
        }

        $aisle = strtoupper(sanitizeString($data['aisle'] ?? '', 10));
        $rack = strtoupper(sanitizeString($data['rack'] ?? '', 10));
        $level = sanitizeString((string) ($data['level'] ?? ''), 10);
        $position = sanitizeString((string) ($data['position'] ?? ''), 10);

        $code = strtoupper(sanitizeString($data['code'] ?? '', 40));
        if ($code === '') {
            if ($aisle === '' || $rack === '' || $level === '' || $position === '') {
                jsonError('Indicá pasillo, estante, nivel y posición (o un código)', 422);
            }
            $code = $aisle . '-' . $rack . '-' . str_pad($level, 2, '0', STR_PAD_LEFT) . '-' . str_pad($position, 2, '0', STR_PAD_LEFT);
        }

        $model = new StorageLocationModel(Database::getConnection());
        if ($model->existsCode($warehouseId, $code)) {
            jsonError('Ya existe una ubicación con ese código en la bodega', 409);
        }

        $id = $model->create([
            'warehouse_id' => $warehouseId,
            'category_id' => isset($data['category_id']) && $data['category_id'] !== '' ? (int) $data['category_id'] : null,
            'aisle' => $aisle ?: null,
            'rack' => $rack ?: null,
            'level' => $level ?: null,
            'position' => $position ?: null,
            'code' => $code,
            'capacity' => max(1, (int) ($data['capacity'] ?? 1)),
            'is_active' => isset($data['is_active']) ? (!empty($data['is_active']) ? 1 : 0) : 1,
        ]);

        jsonSuccess(['storage_location' => $model->findById($id)], 'Ubicación creada', 201);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('storage_location_update', 20, 900);
        if (!hasPermission($payload, 'storage_location', 'edit')) {
            jsonError('No tenés permiso', 403);
        }

        $data = getJsonInput();
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            jsonError('ID inválido', 422);
        }

        $model = new StorageLocationModel(Database::getConnection());
        $existing = $model->findById($id);
        if (!$existing) {
            jsonError('Ubicación no encontrada', 404);
        }

        $warehouseId = (int) ($existing['warehouse_id']);
        $update = [];

        if (array_key_exists('code', $data)) {
            $code = strtoupper(sanitizeString((string) $data['code'], 40));
            if ($code === '' || $model->existsCode($warehouseId, $code, $id)) {
                jsonError('Código inválido o ya en uso', 422);
            }
            $update['code'] = $code;
        }
        foreach (['aisle', 'rack', 'level', 'position'] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = sanitizeString((string) $data[$field], 10) ?: null;
            }
        }
        if (array_key_exists('category_id', $data)) {
            $update['category_id'] = $data['category_id'] !== '' && $data['category_id'] !== null ? (int) $data['category_id'] : null;
        }
        if (array_key_exists('capacity', $data)) {
            $update['capacity'] = max(1, (int) $data['capacity']);
        }
        if (array_key_exists('is_active', $data)) {
            $update['is_active'] = !empty($data['is_active']) ? 1 : 0;
        }

        if (!empty($update)) {
            $model->update($id, $update);
        }

        jsonSuccess(['storage_location' => $model->findById($id)], 'Ubicación actualizada');
    }

    public function delete(): never
    {
        requireMethod('DELETE');
        $payload = requireAuthMiddleware(true);
        rateLimit('storage_location_delete', 5, 900);
        if (!hasPermission($payload, 'storage_location', 'delete')) {
            jsonError('No tenés permiso', 403);
        }

        $data = getJsonInput();
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            jsonError('ID inválido', 422);
        }

        $model = new StorageLocationModel(Database::getConnection());
        if ($model->countPackages($id) > 0) {
            jsonError('No se puede eliminar: la ubicación tiene paquetes', 409);
        }

        $model->delete($id);
        jsonSuccess(['message' => 'Ubicación eliminada']);
    }

    public function generate(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('storage_location_generate', 5, 900);
        if (!hasPermission($payload, 'storage_location', 'create')) {
            jsonError('No tenés permiso', 403);
        }

        $data = getJsonInput();
        $warehouseId = (int) ($data['warehouse_id'] ?? 0);
        if ($warehouseId <= 0 || !(new WarehouseModel(Database::getConnection()))->findById($warehouseId)) {
            jsonError('Bodega inválida', 422);
        }

        $aisle = strtoupper(sanitizeString($data['aisle'] ?? '', 10));
        $rack = strtoupper(sanitizeString($data['rack'] ?? '', 10));
        $levels = (int) ($data['levels'] ?? 0);
        $positions = (int) ($data['positions'] ?? 0);
        if ($aisle === '' || $rack === '' || $levels < 1 || $positions < 1) {
            jsonError('Indicá pasillo, estante, niveles y posiciones', 422);
        }
        if ($levels > 50 || $positions > 100) {
            jsonError('Máximo 50 niveles y 100 posiciones por generación', 422);
        }

        $created = (new StorageLocationModel(Database::getConnection()))->generate(
            $warehouseId,
            $aisle,
            $rack,
            $levels,
            $positions,
            max(1, (int) ($data['capacity'] ?? 1)),
            isset($data['category_id']) && $data['category_id'] !== '' ? (int) $data['category_id'] : null
        );

        jsonSuccess(['created' => $created], "Se generaron {$created} ubicaciones");
    }
}
