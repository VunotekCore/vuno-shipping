<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\StorageCategoryModel;
use App\Config\Database;

class StorageCategoryController
{
    private StorageCategoryModel $model;

    public function __construct()
    {
        $this->model = new StorageCategoryModel(Database::getConnection());
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();
        if (!hasPermission($payload, 'storage_category', 'read')) {
            jsonError('No tenés permiso', 403);
        }

        if (isset($_GET['all'])) {
            jsonSuccess(['storage_categories' => $this->model->listAll()]);
        }

        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->model->count();
        $items = $this->model->listPaginated($limit, $offset);

        jsonSuccess([
            'storage_categories' => $items,
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
        rateLimit('storage_category_create', 10, 900);
        if (!hasPermission($payload, 'storage_category', 'create')) {
            jsonError('No tenés permiso', 403);
        }

        $data = getJsonInput();
        $name = sanitizeString($data['name'] ?? '', 60);
        if ($name === '') {
            jsonError('El nombre es requerido', 422);
        }
        if ($this->model->findByName($name)) {
            jsonError('Ya existe una clasificación con ese nombre', 409);
        }

        $id = $this->model->create([
            'name' => $name,
            'color' => sanitizeString($data['color'] ?? '', 20) ?: null,
            'description' => sanitizeString($data['description'] ?? '', 255) ?: null,
            'is_active' => isset($data['is_active']) ? (!empty($data['is_active']) ? 1 : 0) : 1,
        ]);

        jsonSuccess(['storage_category' => $this->model->findById($id)], 'Clasificación creada', 201);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('storage_category_update', 20, 900);
        if (!hasPermission($payload, 'storage_category', 'edit')) {
            jsonError('No tenés permiso', 403);
        }

        $data = getJsonInput();
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            jsonError('ID inválido', 422);
        }

        $existing = $this->model->findById($id);
        if (!$existing) {
            jsonError('Clasificación no encontrada', 404);
        }

        $update = [];
        if (array_key_exists('name', $data)) {
            $name = sanitizeString((string) $data['name'], 60);
            if ($name === '') {
                jsonError('El nombre es requerido', 422);
            }
            $dup = $this->model->findByName($name);
            if ($dup && (int) $dup['id'] !== $id) {
                jsonError('Ya existe una clasificación con ese nombre', 409);
            }
            $update['name'] = $name;
        }
        if (array_key_exists('color', $data)) {
            $update['color'] = sanitizeString((string) $data['color'], 20) ?: null;
        }
        if (array_key_exists('description', $data)) {
            $update['description'] = sanitizeString((string) $data['description'], 255) ?: null;
        }
        if (array_key_exists('is_active', $data)) {
            $update['is_active'] = !empty($data['is_active']) ? 1 : 0;
        }

        if (!empty($update)) {
            $this->model->update($id, $update);
        }

        jsonSuccess(['storage_category' => $this->model->findById($id)], 'Clasificación actualizada');
    }

    public function delete(): never
    {
        requireMethod('DELETE');
        $payload = requireAuthMiddleware(true);
        rateLimit('storage_category_delete', 5, 900);
        if (!hasPermission($payload, 'storage_category', 'delete')) {
            jsonError('No tenés permiso', 403);
        }

        $data = getJsonInput();
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            jsonError('ID inválido', 422);
        }

        if ($this->model->countPackages($id) > 0) {
            jsonError('No se puede eliminar: hay paquetes con esta clasificación', 409);
        }

        $this->model->delete($id);
        jsonSuccess(['message' => 'Clasificación eliminada']);
    }
}
