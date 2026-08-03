<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\RoleModel;
use App\Services\AuthService;
use App\Config\Database;

class UserController
{
    private UserModel $model;
    private AuthService $authService;

    public function __construct()
    {
        $this->model = new UserModel(Database::getConnection());
        $this->authService = new AuthService();
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'user', 'read')) {
            jsonError('No tenés permiso para ver usuarios', 403);
        }

        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->model->count();
        $users = $this->model->listPaginated($limit, $offset);

        jsonSuccess([
            'users' => $users,
            'pagination' => [
                'page'  => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    public function listByRole(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'user', 'read')) {
            jsonError('No tenés permiso', 403);
        }

        $slug = $_GET['role'] ?? '';
        if ($slug === '') jsonError('role es requerido', 422);

        $users = $this->model->listByRole($slug);
        jsonSuccess(['users' => $users]);
    }

    public function create(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('user_create', 10, 900);

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
        validatePassword($password);

        $existing = $this->model->findByEmail($email);
        if ($existing !== null) {
            jsonError('Ya existe un usuario con ese email', 409);
        }

        $roleModel = new RoleModel(Database::getConnection());
        $role = $roleModel->findById($roleId);
        if ($role === null) {
            jsonError('El rol seleccionado no existe', 422);
        }

        $hash = $this->authService->hashPassword($password);
        $id = $this->model->create($email, $hash, $name, $roleId);
        $user = $this->model->findById($id);

        jsonSuccess(['user' => $user], 'Usuario creado', 201);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('user_update', 20, 900);

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

        if (isset($data['role_id']) && (int) $data['role_id'] !== (int) $existing['role_id']) {
            $newRoleId = (int) $data['role_id'];

            if ((int) $payload['sub'] === $id) {
                jsonError('No podés cambiar tu propio rol', 409);
            }

            $roleModel = new RoleModel(Database::getConnection());
            $newRole = $roleModel->findById($newRoleId);
            if ($newRole === null) {
                jsonError('El rol seleccionado no existe', 422);
            }

            if ($existing['role_slug'] === 'admin' && $newRole['slug'] !== 'admin') {
                $adminCount = $this->model->countByRoleSlug('admin');
                if ($adminCount <= 1) {
                    jsonError('No se puede degradar: es el último administrador del sistema', 409);
                }
            }

            $updateData['role_id'] = $newRoleId;
        }

        if (!empty($data['password'])) {
            validatePassword($data['password']);
            $updateData['password'] = $this->authService->hashPassword($data['password']);
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
        $payload = requireAuthMiddleware(true);
        rateLimit('user_delete', 5, 900);

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

        if ($existing['role_slug'] === 'admin') {
            $adminCount = $this->model->countByRoleSlug('admin');
            if ($adminCount <= 1) {
                jsonError('No se puede eliminar: es el último administrador del sistema', 409);
            }
        }

        $this->model->delete($id);
        jsonSuccess(null, 'Usuario eliminado');
    }

    public function permissions(): never
    {
        requireMethod('GET', 'PUT');
        $payload = requireAuthMiddleware($_SERVER['REQUEST_METHOD'] === 'PUT');

        if (!hasPermission($payload, 'user', 'read')) {
            jsonError('No tenés permiso para ver usuarios', 403);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $id = (int) ($_GET['id'] ?? 0);
        } else {
            $data = getJsonInput();
            $id   = (int) ($data['id'] ?? 0);
        }

        if ($id <= 0) {
            jsonError('ID de usuario inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Usuario no encontrado', 404);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $permissions = $this->model->getPermissions($id);
            jsonSuccess(['permissions' => $permissions]);
        }

        if (!hasPermission($payload, 'user', 'edit')) {
            jsonError('No tenés permiso para asignar permisos', 403);
        }

        rateLimit('user_permissions', 10, 900);

        $data = getJsonInput();
        $grantedIds = $data['granted_ids'] ?? [];
        $revokedIds = $data['revoked_ids'] ?? [];

        if (!is_array($grantedIds) || !is_array($revokedIds)) {
            jsonError('granted_ids y revoked_ids deben ser arrays', 422);
        }

        $this->model->setPermissions($id, $grantedIds, $revokedIds);
        $updated = $this->model->getPermissions($id);

        jsonSuccess(['permissions' => $updated], 'Permisos actualizados');
    }
}
