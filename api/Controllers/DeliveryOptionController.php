<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\DeliveryOptionModel;
use App\Config\Database;

class DeliveryOptionController
{
    private DeliveryOptionModel $model;

    public function __construct()
    {
        $this->model = new DeliveryOptionModel(Database::getConnection());
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();
        if (!hasPermission($payload, 'delivery_option', 'read')) {
            jsonError('No tenés permiso', 403);
        }
        $items = $this->model->listAll();
        jsonSuccess(['delivery_options' => $items]);
    }

    public function create(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('delivery_option_create', 10, 900);
        if (!hasPermission($payload, 'delivery_option', 'create')) {
            jsonError('No tenés permiso', 403);
        }
        $data = getJsonInput();
        $name = sanitizeString($data['name'] ?? '', 100);
        if ($name === '') {
            jsonError('El nombre es requerido', 422);
        }
        $existing = $this->model->findByName($name);
        if ($existing) {
            jsonError("Ya existe una opción con el nombre \"$name\"", 409);
        }
        $id = $this->model->create([
            'name' => $name,
            'description' => sanitizeString($data['description'] ?? '', 500) ?: null,
            'is_active' => $data['is_active'] ?? true,
        ]);
        jsonSuccess(['delivery_option' => ['id' => $id, 'name' => $name]], 'Created', 201);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('delivery_option_update', 20, 900);
        if (!hasPermission($payload, 'delivery_option', 'edit')) {
            jsonError('No tenés permiso', 403);
        }
        $data = getJsonInput();
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            jsonError('ID inválido', 422);
        }
        $existing = $this->model->findById($id);
        if (!$existing) {
            jsonError('Opción no encontrada', 404);
        }
        $name = sanitizeString($data['name'] ?? $existing['name'], 100);
        if ($name === '') {
            jsonError('El nombre es requerido', 422);
        }
        if (strtolower($name) !== strtolower($existing['name'])) {
            $dup = $this->model->findByName($name);
            if ($dup) {
                jsonError("Ya existe una opción con el nombre \"$name\"", 409);
            }
        }
        $this->model->update([
            'id' => $id,
            'name' => $name,
            'description' => array_key_exists('description', $data) ? (sanitizeString($data['description'] ?? '', 500) ?: null) : $existing['description'],
            'is_active' => $data['is_active'] ?? $existing['is_active'],
        ]);
        jsonSuccess(['message' => 'Actualizado']);
    }

    public function delete(): never
    {
        requireMethod('DELETE');
        $payload = requireAuthMiddleware(true);
        rateLimit('delivery_option_delete', 5, 900);
        if (!hasPermission($payload, 'delivery_option', 'delete')) {
            jsonError('No tenés permiso', 403);
        }
        $data = getJsonInput();
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            jsonError('ID inválido', 422);
        }
        $this->model->delete($id);
        jsonSuccess(['message' => 'Eliminado']);
    }
}
