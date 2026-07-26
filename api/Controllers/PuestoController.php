<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\PuestoModel;
use App\Models\DireccionModel;
use App\Config\Database;

class PuestoController
{
    private PuestoModel $model;

    public function __construct()
    {
        $this->model = new PuestoModel(Database::getConnection());
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'puesto', 'read')) {
            jsonError('No tenés permiso para ver puestos', 403);
        }

        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->model->count();
        $puestos = $this->model->listPaginated($limit, $offset);

        jsonSuccess([
            'puestos' => $puestos,
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

        if (!hasPermission($payload, 'puesto', 'read')) {
            jsonError('No tenés permiso para ver puestos', 403);
        }

        $puestos = $this->model->listAll();
        jsonSuccess(['puestos' => $puestos]);
    }

    public function create(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('puesto_create', 10, 900);

        if (!hasPermission($payload, 'puesto', 'create')) {
            jsonError('No tenés permiso para crear puestos', 403);
        }

        $data = getJsonInput();

        $name = sanitizeString($data['name'] ?? '', 100);
        $description = isset($data['description']) ? trim($data['description']) : null;
        $direccionId = (int) ($data['direccion_id'] ?? 0);

        if ($name === '') {
            jsonError('El nombre es requerido', 422);
        }

        if ($direccionId <= 0) {
            jsonError('La dirección es requerida', 422);
        }

        $direccionModel = new DireccionModel(Database::getConnection());
        $direccion = $direccionModel->findById($direccionId);
        if ($direccion === null) {
            jsonError('La dirección seleccionada no existe', 422);
        }

        $id = $this->model->create($name, $description, $direccionId);
        $puesto = $this->model->findById($id);

        jsonSuccess(['puesto' => $puesto], 'Puesto creado', 201);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('puesto_update', 20, 900);

        if (!hasPermission($payload, 'puesto', 'edit')) {
            jsonError('No tenés permiso para editar puestos', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            jsonError('ID de puesto inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Puesto no encontrado', 404);
        }

        $name = sanitizeString($data['name'] ?? $existing['name'], 100);
        $description = array_key_exists('description', $data)
            ? ($data['description'] !== null ? trim($data['description']) : null)
            : $existing['description'];
        $direccionId = (int) ($data['direccion_id'] ?? $existing['direccion_id']);

        if ($name === '') {
            jsonError('El nombre es requerido', 422);
        }

        if ($direccionId !== (int) $existing['direccion_id']) {
            $direccionModel = new DireccionModel(Database::getConnection());
            $direccion = $direccionModel->findById($direccionId);
            if ($direccion === null) {
                jsonError('La dirección seleccionada no existe', 422);
            }
        }

        $this->model->update($id, $name, $description, $direccionId);
        $updated = $this->model->findById($id);

        jsonSuccess(['puesto' => $updated], 'Puesto actualizado');
    }

    public function delete(): never
    {
        requireMethod('DELETE');
        $payload = requireAuthMiddleware(true);
        rateLimit('puesto_delete', 5, 900);

        if (!hasPermission($payload, 'puesto', 'delete')) {
            jsonError('No tenés permiso para eliminar puestos', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            jsonError('ID de puesto inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Puesto no encontrado', 404);
        }

        $empleadoCount = $this->model->countEmpleados($id);
        if ($empleadoCount > 0) {
            jsonError("No se puede eliminar: hay {$empleadoCount} empleado(s) en este puesto. Desasociá los empleados primero.", 409);
        }

        $this->model->delete($id);
        jsonSuccess(null, 'Puesto eliminado');
    }
}
