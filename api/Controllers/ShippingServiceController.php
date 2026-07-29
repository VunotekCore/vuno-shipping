<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\ShippingServiceModel;
use App\Config\Database;

class ShippingServiceController
{
    private ShippingServiceModel $model;

    public function __construct()
    {
        $this->model = new ShippingServiceModel(Database::getConnection());
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();
        if (!hasPermission($payload, 'shipping_service', 'read')) {
            jsonError('No tenés permiso', 403);
        }
        $items = $this->model->listAll();
        jsonSuccess(['shipping_services' => $items]);
    }

    public function create(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('shipping_service_create', 10, 900);
        if (!hasPermission($payload, 'shipping_service', 'create')) {
            jsonError('No tenés permiso', 403);
        }
        $data = getJsonInput();
        $name = sanitizeString($data['name'] ?? '', 100);
        if ($name === '') {
            jsonError('El nombre es requerido', 422);
        }
        $deliveryTypeId = isset($data['delivery_type_id']) ? (int) $data['delivery_type_id'] : null;
        if ($deliveryTypeId !== null) {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT 1 FROM delivery_type WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $deliveryTypeId]);
            if (!$stmt->fetch()) {
                jsonError('El tipo de entrega especificado no existe', 422);
            }
        }
        $id = $this->model->create([
            'name' => $name,
            'description' => sanitizeString($data['description'] ?? '', 500) ?: null,
            'is_active' => $data['is_active'] ?? true,
            'delivery_type_id' => $deliveryTypeId,
        ]);
        jsonSuccess(['shipping_service' => ['id' => $id, 'name' => $name]], 201);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('shipping_service_update', 20, 900);
        if (!hasPermission($payload, 'shipping_service', 'edit')) {
            jsonError('No tenés permiso', 403);
        }
        $data = getJsonInput();
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            jsonError('ID inválido', 422);
        }
        $existing = $this->model->findById($id);
        if (!$existing) {
            jsonError('Servicio no encontrado', 404);
        }
        $name = sanitizeString($data['name'] ?? $existing['name'], 100);
        if ($name === '') {
            jsonError('El nombre es requerido', 422);
        }
        if (array_key_exists('delivery_type_id', $data)) {
            $deliveryTypeId = $data['delivery_type_id'] !== null ? (int) $data['delivery_type_id'] : null;
            if ($deliveryTypeId !== null) {
                $db = Database::getConnection();
                $stmt = $db->prepare('SELECT 1 FROM delivery_type WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $deliveryTypeId]);
                if (!$stmt->fetch()) {
                    jsonError('El tipo de entrega especificado no existe', 422);
                }
            }
            $existing['delivery_type_id'] = $deliveryTypeId;
        }
        $this->model->update([
            'id' => $id,
            'name' => $name,
            'description' => array_key_exists('description', $data) ? (sanitizeString($data['description'] ?? '', 500) ?: null) : $existing['description'],
            'is_active' => $data['is_active'] ?? $existing['is_active'],
            'delivery_type_id' => $existing['delivery_type_id'],
        ]);
        jsonSuccess(['message' => 'Actualizado']);
    }

    public function delete(): never
    {
        requireMethod('DELETE');
        $payload = requireAuthMiddleware(true);
        rateLimit('shipping_service_delete', 5, 900);
        if (!hasPermission($payload, 'shipping_service', 'delete')) {
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
