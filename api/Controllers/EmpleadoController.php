<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\EmpleadoModel;
use App\Models\PuestoModel;
use App\Models\UserModel;
use App\Config\Database;

class EmpleadoController
{
    private EmpleadoModel $model;

    public function __construct()
    {
        $this->model = new EmpleadoModel(Database::getConnection());
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'empleado', 'read')) {
            jsonError('No tenés permiso para ver empleados', 403);
        }

        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->model->count();
        $empleados = $this->model->listPaginated($limit, $offset);

        jsonSuccess([
            'empleados' => $empleados,
            'pagination' => [
                'page'  => $page,
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
        rateLimit('empleado_create', 10, 900);

        if (!hasPermission($payload, 'empleado', 'create')) {
            jsonError('No tenés permiso para crear empleados', 403);
        }

        $data = getJsonInput();

        $name = sanitizeString($data['name'] ?? '', 100);
        $email = isset($data['email']) && $data['email'] !== '' ? validateEmail($data['email']) : null;
        $phone = isset($data['phone']) ? sanitizeString($data['phone'], 30) : null;
        $cedula = isset($data['cedula']) && $data['cedula'] !== '' ? sanitizeString($data['cedula'], 30) : null;
        $fechaIngreso = $data['fecha_ingreso'] ?? null;
        $puestoId = (int) ($data['puesto_id'] ?? 0);
        $userId = isset($data['user_id']) && $data['user_id'] !== '' ? (int) $data['user_id'] : null;
        $isActive = (int) ($data['is_active'] ?? 1);

        if ($name === '') {
            jsonError('El nombre es requerido', 422);
        }

        if ($puestoId <= 0) {
            jsonError('El puesto es requerido', 422);
        }

        $puestoModel = new PuestoModel(Database::getConnection());
        $puesto = $puestoModel->findById($puestoId);
        if ($puesto === null) {
            jsonError('El puesto seleccionado no existe', 422);
        }

        if ($cedula !== null) {
            $conflict = $this->model->findByCedula($cedula);
            if ($conflict !== null) {
                jsonError('Ya existe un empleado con esa cédula', 409);
            }
        }

        if ($userId !== null) {
            $userModel = new UserModel(Database::getConnection());
            $user = $userModel->findById($userId);
            if ($user === null) {
                jsonError('El usuario seleccionado no existe', 422);
            }
            $userConflict = $this->model->findByUserId($userId);
            if ($userConflict !== null) {
                jsonError('Ese usuario ya está asignado a otro empleado', 409);
            }
        }

        $id = $this->model->create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'cedula' => $cedula,
            'fecha_ingreso' => $fechaIngreso,
            'puesto_id' => $puestoId,
            'user_id' => $userId,
            'is_active' => $isActive,
        ]);

        $empleado = $this->model->findById($id);
        jsonSuccess(['empleado' => $empleado], 'Empleado creado', 201);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('empleado_update', 20, 900);

        if (!hasPermission($payload, 'empleado', 'edit')) {
            jsonError('No tenés permiso para editar empleados', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            jsonError('ID de empleado inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Empleado no encontrado', 404);
        }

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = sanitizeString($data['name'], 100);
            if ($updateData['name'] === '') {
                jsonError('El nombre es requerido', 422);
            }
        }

        if (array_key_exists('email', $data)) {
            $updateData['email'] = $data['email'] !== '' && $data['email'] !== null
                ? validateEmail($data['email'])
                : null;
        }

        if (array_key_exists('phone', $data)) {
            $updateData['phone'] = $data['phone'] !== null ? sanitizeString($data['phone'], 30) : null;
        }

        if (array_key_exists('cedula', $data)) {
            $cedula = $data['cedula'] !== '' && $data['cedula'] !== null ? sanitizeString($data['cedula'], 30) : null;
            if ($cedula !== null) {
                $conflict = $this->model->findByCedula($cedula, $id);
                if ($conflict !== null) {
                    jsonError('Ya existe un empleado con esa cédula', 409);
                }
            }
            $updateData['cedula'] = $cedula;
        }

        if (array_key_exists('fecha_ingreso', $data)) {
            $updateData['fecha_ingreso'] = $data['fecha_ingreso'] ?: null;
        }

        if (isset($data['puesto_id'])) {
            $puestoId = (int) $data['puesto_id'];
            if ($puestoId !== (int) $existing['puesto_id']) {
                $puestoModel = new PuestoModel(Database::getConnection());
                $puesto = $puestoModel->findById($puestoId);
                if ($puesto === null) {
                    jsonError('El puesto seleccionado no existe', 422);
                }
            }
            $updateData['puesto_id'] = $puestoId;
        }

        if (array_key_exists('user_id', $data)) {
            $userId = $data['user_id'] !== '' && $data['user_id'] !== null ? (int) $data['user_id'] : null;
            if ($userId !== null && $userId !== (int) ($existing['user_id'] ?? 0)) {
                $userModel = new UserModel(Database::getConnection());
                $user = $userModel->findById($userId);
                if ($user === null) {
                    jsonError('El usuario seleccionado no existe', 422);
                }
                $userConflict = $this->model->findByUserId($userId, $id);
                if ($userConflict !== null) {
                    jsonError('Ese usuario ya está asignado a otro empleado', 409);
                }
            }
            $updateData['user_id'] = $userId;
        }

        if (array_key_exists('is_active', $data)) {
            $updateData['is_active'] = (int) $data['is_active'];
        }

        if (empty($updateData)) {
            jsonError('No hay datos para actualizar', 422);
        }

        $this->model->update($id, $updateData);
        $updated = $this->model->findById($id);

        jsonSuccess(['empleado' => $updated], 'Empleado actualizado');
    }

    public function delete(): never
    {
        requireMethod('DELETE');
        $payload = requireAuthMiddleware(true);
        rateLimit('empleado_delete', 5, 900);

        if (!hasPermission($payload, 'empleado', 'delete')) {
            jsonError('No tenés permiso para eliminar empleados', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            jsonError('ID de empleado inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Empleado no encontrado', 404);
        }

        $this->model->delete($id);
        jsonSuccess(null, 'Empleado eliminado');
    }
}
