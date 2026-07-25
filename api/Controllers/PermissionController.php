<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\PermissionModel;
use App\Config\Database;

class PermissionController
{
    private PermissionModel $model;

    public function __construct()
    {
        $this->model = new PermissionModel(Database::getConnection());
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'permission', 'read')) {
            jsonError('No tenés permiso para ver permisos', 403);
        }

        $permissions = $this->model->list();
        jsonSuccess(['permissions' => $permissions]);
    }

    public function create(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware();
        rateLimit('permission_create', 10, 900);

        if (!hasPermission($payload, 'permission', 'create')) {
            jsonError('No tenés permiso para crear permisos', 403);
        }

        $data = getJsonInput();

        $module = sanitizeString($data['module'] ?? '', 50);
        $action = sanitizeString($data['action'] ?? '', 30);
        $label  = sanitizeString($data['label'] ?? '', 100);

        if ($module === '' || $action === '') {
            jsonError('Módulo y acción son requeridos', 422);
        }

        if (!preg_match('/^[a-z][a-z0-9_]*$/', $module)) {
            jsonError('El módulo solo puede contener letras minúsculas, números y guiones bajos', 422);
        }

        if (!preg_match('/^[a-z][a-z0-9_]*$/', $action)) {
            jsonError('La acción solo puede contener letras minúsculas, números y guiones bajos', 422);
        }

        $existing = $this->model->findByModuleAction($module, $action);
        if ($existing !== null) {
            jsonError('Ya existe un permiso para este módulo y acción', 409);
        }

        $id = $this->model->create($module, $action, $label);
        $permission = $this->model->findById($id);

        jsonSuccess(['permission' => $permission], 'Permiso creado', 201);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware();
        rateLimit('permission_update', 20, 900);

        if (!hasPermission($payload, 'permission', 'edit')) {
            jsonError('No tenés permiso para editar permisos', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            jsonError('ID de permiso inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Permiso no encontrado', 404);
        }

        $module = sanitizeString($data['module'] ?? $existing['module'], 50);
        $action = sanitizeString($data['action'] ?? $existing['action'], 30);
        $label  = sanitizeString($data['label'] ?? $existing['label'] ?? '', 100);

        if ($module === '' || $action === '') {
            jsonError('Módulo y acción son requeridos', 422);
        }

        if (!preg_match('/^[a-z][a-z0-9_]*$/', $module)) {
            jsonError('El módulo solo puede contener letras minúsculas, números y guiones bajos', 422);
        }

        if (!preg_match('/^[a-z][a-z0-9_]*$/', $action)) {
            jsonError('La acción solo puede contener letras minúsculas, números y guiones bajos', 422);
        }

        $duplicate = $this->model->findByModuleAction($module, $action);
        if ($duplicate !== null && $duplicate['id'] !== $id) {
            jsonError('Ya existe un permiso para este módulo y acción', 409);
        }

        $this->model->update($id, $module, $action, $label);
        $updated = $this->model->findById($id);

        jsonSuccess(['permission' => $updated], 'Permiso actualizado');
    }

    public function delete(): never
    {
        requireMethod('DELETE');
        $payload = requireAuthMiddleware();
        rateLimit('permission_delete', 5, 900);

        if (!hasPermission($payload, 'permission', 'delete')) {
            jsonError('No tenés permiso para eliminar permisos', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            jsonError('ID de permiso inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Permiso no encontrado', 404);
        }

        $rolesCount   = $this->model->countRolesWithPermission($id);
        $usersCount   = $this->model->countUsersWithPermission($id);

        if ($rolesCount > 0 || $usersCount > 0) {
            jsonError(
                "Este permiso está asignado a {$rolesCount} rol(es) y {$usersCount} usuario(s). Desasignalo antes de eliminar.",
                409
            );
        }

        $this->model->delete($id);
        jsonSuccess(null, 'Permiso eliminado');
    }
}
