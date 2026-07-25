<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\RoleModel;
use App\Config\Database;

class RoleController
{
    private RoleModel $model;

    public function __construct()
    {
        $this->model = new RoleModel(Database::getConnection());
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'role', 'read')) {
            jsonError('No tenés permiso para ver roles', 403);
        }

        $roles = $this->model->list();
        jsonSuccess(['roles' => $roles]);
    }

    public function create(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'role', 'create')) {
            jsonError('No tenés permiso para crear roles', 403);
        }

        $data = getJsonInput();

        $name = sanitizeString($data['name'] ?? '', 50);
        $slug = slugify($data['slug'] ?? $name);

        if ($name === '') {
            jsonError('El nombre es requerido', 422);
        }

        if (!preg_match('/^[a-z][a-z0-9_-]*$/', $slug)) {
            jsonError('El slug solo puede contener letras minúsculas, números, guiones y guiones bajos', 422);
        }

        $existing = $this->model->findBySlug($slug);
        if ($existing !== null) {
            jsonError('Ya existe un rol con ese slug', 409);
        }

        $id = $this->model->create($name, $slug);
        $role = $this->model->findById($id);

        jsonSuccess(['role' => $role], 'Rol creado', 201);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'role', 'edit')) {
            jsonError('No tenés permiso para editar roles', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            jsonError('ID de rol inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Rol no encontrado', 404);
        }

        $name = sanitizeString($data['name'] ?? $existing['name'], 50);
        $slug = slugify($data['slug'] ?? $name);

        if ($name === '') {
            jsonError('El nombre es requerido', 422);
        }

        if (!preg_match('/^[a-z][a-z0-9_-]*$/', $slug)) {
            jsonError('El slug solo puede contener letras minúsculas, números, guiones y guiones bajos', 422);
        }

        $duplicate = $this->model->findBySlug($slug);
        if ($duplicate !== null && $duplicate['id'] !== $id) {
            jsonError('Ya existe un rol con ese slug', 409);
        }

        $this->model->update($id, $name, $slug);
        $updated = $this->model->findById($id);

        jsonSuccess(['role' => $updated], 'Rol actualizado');
    }

    public function delete(): never
    {
        requireMethod('DELETE');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'role', 'delete')) {
            jsonError('No tenés permiso para eliminar roles', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            jsonError('ID de rol inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Rol no encontrado', 404);
        }

        $userCount = $this->model->countUsers($id);
        if ($userCount > 0) {
            jsonError("No se puede eliminar: hay {$userCount} usuario(s) con este rol. Cambiá su rol primero.", 409);
        }

        $this->model->delete($id);
        jsonSuccess(null, 'Rol eliminado');
    }

    public function permissions(): never
    {
        requireMethod('GET', 'PUT');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'role', 'read')) {
            jsonError('No tenés permiso para ver roles', 403);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $id = (int) ($_GET['id'] ?? 0);
        } else {
            $data = getJsonInput();
            $id   = (int) ($data['id'] ?? 0);
        }

        if ($id <= 0) {
            jsonError('ID de rol inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Rol no encontrado', 404);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $permissions = $this->model->getPermissions($id);
            jsonSuccess(['permissions' => $permissions]);
        }

        if (!hasPermission($payload, 'role', 'edit')) {
            jsonError('No tenés permiso para asignar permisos', 403);
        }

        $data = getJsonInput();
        $permissionIds = $data['permission_ids'] ?? [];
        if (!is_array($permissionIds)) {
            jsonError('permission_ids debe ser un array', 422);
        }

        $this->model->setPermissions($id, $permissionIds);
        $updated = $this->model->getPermissions($id);

        jsonSuccess(['permissions' => $updated], 'Permisos actualizados');
    }
}
