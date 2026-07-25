<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserModel;
use App\Config\Database;

class UserController
{
    private UserModel $model;

    public function __construct()
    {
        $this->model = new UserModel(Database::getConnection());
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'user', 'read')) {
            jsonError('No tenés permiso para ver usuarios', 403);
        }

        $users = $this->model->list();
        jsonSuccess(['users' => $users]);
    }

    public function create(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'user', 'create')) {
            jsonError('No tenés permiso para crear usuarios', 403);
        }

        $data = getJsonInput();

        $email    = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $name     = $data['name'] ?? '';
        $roleId   = (int) ($data['role_id'] ?? 0);

        if ($email === '' || $password === '' || $name === '' || $roleId <= 0) {
            jsonError('Email, contraseña, nombre y rol son requeridos', 422);
        }

        $email    = validateEmail($email);
        $name     = sanitizeString($name, 100);
        validatePassword($password, true);

        $existing = $this->model->findByEmail($email);
        if ($existing !== null) {
            jsonError('Ya existe un usuario con ese email', 409);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $id = $this->model->create($email, $hash, $name, $roleId);
        $user = $this->model->findById($id);

        jsonSuccess(['user' => $user], 'Usuario creado', 201);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'user', 'edit')) {
            jsonError('No tenés permiso para editar usuarios', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            jsonError('ID de usuario inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Usuario no encontrado', 404);
        }

        $updateData = [];

        if (isset($data['email']) && $data['email'] !== $existing['email']) {
            $email = validateEmail($data['email']);
            $conflict = $this->model->findByEmail($email);
            if ($conflict !== null) {
                jsonError('Ya existe un usuario con ese email', 409);
            }
            $updateData['email'] = $email;
        }

        if (isset($data['name']) && $data['name'] !== $existing['name']) {
            $updateData['name'] = sanitizeString($data['name'], 100);
        }

        if (isset($data['role_id']) && (int) $data['role_id'] !== 0) {
            $updateData['role_id'] = (int) $data['role_id'];
        }

        if (!empty($data['password'])) {
            validatePassword($data['password'], false);
            $updateData['password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }

        if (empty($updateData)) {
            jsonError('No hay datos para actualizar', 422);
        }

        $this->model->update($id, $updateData);
        $updated = $this->model->findById($id);

        jsonSuccess(['user' => $updated], 'Usuario actualizado');
    }

    public function delete(): never
    {
        requireMethod('DELETE');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'user', 'delete')) {
            jsonError('No tenés permiso para eliminar usuarios', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            jsonError('ID de usuario inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Usuario no encontrado', 404);
        }

        if ((int) $payload['sub'] === $id) {
            jsonError('No podés eliminar tu propio usuario', 409);
        }

        $this->model->delete($id);
        jsonSuccess(null, 'Usuario eliminado');
    }
}
