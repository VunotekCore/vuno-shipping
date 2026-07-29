<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\DeliveryTypeModel;
use App\Config\Database;

class DeliveryTypeController
{
    private DeliveryTypeModel $model;

    public function __construct()
    {
        $this->model = new DeliveryTypeModel(Database::getConnection());
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();
        if (!hasPermission($payload, 'delivery_type', 'read')) {
            jsonError('No tenés permiso', 403);
        }
        $items = $this->model->listAll();
        jsonSuccess(['delivery_types' => $items]);
    }

    public function create(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('delivery_type_create', 10, 900);
        if (!hasPermission($payload, 'delivery_type', 'create')) {
            jsonError('No tenés permiso', 403);
        }
        $data = getJsonInput();
        $name = sanitizeString($data['name'] ?? '', 50);
        if ($name === '') {
            jsonError('El nombre es requerido', 422);
        }
        $category = sanitizeString($data['category'] ?? 'international', 20);
        if (!in_array($category, ['international', 'local'], true)) {
            $category = 'international';
        }
        $id = $this->model->create([
            'name' => $name,
            'description' => sanitizeString($data['description'] ?? '', 255) ?: null,
            'category' => $category,
        ]);
        jsonSuccess(['delivery_type' => ['id' => $id, 'name' => $name]], 201);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('delivery_type_update', 20, 900);
        if (!hasPermission($payload, 'delivery_type', 'edit')) {
            jsonError('No tenés permiso', 403);
        }
        $data = getJsonInput();
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            jsonError('ID inválido', 422);
        }
        $existing = $this->model->findById($id);
        if (!$existing) {
            jsonError('Tipo de entrega no encontrado', 404);
        }
        $name = sanitizeString($data['name'] ?? $existing['name'], 50);
        if ($name === '') {
            jsonError('El nombre es requerido', 422);
        }
        $category = sanitizeString($data['category'] ?? $existing['category'], 20);
        if (!in_array($category, ['international', 'local'], true)) {
            $category = $existing['category'];
        }
        $this->model->update([
            'id' => $id,
            'name' => $name,
            'description' => array_key_exists('description', $data) ? (sanitizeString($data['description'] ?? '', 255) ?: null) : $existing['description'],
            'category' => $category,
        ]);
        jsonSuccess(['message' => 'Actualizado']);
    }

    public function delete(): never
    {
        requireMethod('DELETE');
        $payload = requireAuthMiddleware(true);
        rateLimit('delivery_type_delete', 5, 900);
        if (!hasPermission($payload, 'delivery_type', 'delete')) {
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
