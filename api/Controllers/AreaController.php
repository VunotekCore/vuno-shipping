<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AreaModel;
use App\Config\Database;

class AreaController
{
    private AreaModel $model;

    public function __construct()
    {
        $this->model = new AreaModel(Database::getConnection());
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'area', 'read')) {
            jsonError('No tenés permiso para ver áreas', 403);
        }

        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->model->count();
        $areas = $this->model->listPaginated($limit, $offset);

        jsonSuccess([
            'areas' => $areas,
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

        if (!hasPermission($payload, 'area', 'read')) {
            jsonError('No tenés permiso para ver áreas', 403);
        }

        $areas = $this->model->listAll();
        jsonSuccess(['areas' => $areas]);
    }

    public function create(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('area_create', 10, 900);

        if (!hasPermission($payload, 'area', 'create')) {
            jsonError('No tenés permiso para crear áreas', 403);
        }

        $data = getJsonInput();

        $name = sanitizeString($data['name'] ?? '', 100);
        $description = isset($data['description']) ? trim($data['description']) : null;

        if ($name === '') {
            jsonError('El nombre es requerido', 422);
        }

        $id = $this->model->create($name, $description);
        $area = $this->model->findById($id);

        jsonSuccess(['area' => $area], 'Área creada', 201);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('area_update', 20, 900);

        if (!hasPermission($payload, 'area', 'edit')) {
            jsonError('No tenés permiso para editar áreas', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            jsonError('ID de área inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Área no encontrada', 404);
        }

        $name = sanitizeString($data['name'] ?? $existing['name'], 100);
        $description = array_key_exists('description', $data)
            ? ($data['description'] !== null ? trim($data['description']) : null)
            : $existing['description'];

        if ($name === '') {
            jsonError('El nombre es requerido', 422);
        }

        $this->model->update($id, $name, $description);
        $updated = $this->model->findById($id);

        jsonSuccess(['area' => $updated], 'Área actualizada');
    }

    public function delete(): never
    {
        requireMethod('DELETE');
        $payload = requireAuthMiddleware(true);
        rateLimit('area_delete', 5, 900);

        if (!hasPermission($payload, 'area', 'delete')) {
            jsonError('No tenés permiso para eliminar áreas', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            jsonError('ID de área inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Área no encontrada', 404);
        }

        $puestoCount = $this->model->countPuestos($id);
        if ($puestoCount > 0) {
            jsonError("No se puede eliminar: hay {$puestoCount} puesto(s) asociados. Desasociá los puestos primero.", 409);
        }

        $this->model->delete($id);
        jsonSuccess(null, 'Área eliminada');
    }
}
