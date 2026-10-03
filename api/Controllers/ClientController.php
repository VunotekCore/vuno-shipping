<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\ClientModel;
use App\Config\Database;

class ClientController
{
    private ClientModel $model;

    public function __construct()
    {
        $this->model = new ClientModel(Database::getConnection());
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'client', 'read')) {
            jsonError('No tenés permiso para ver clientes', 403);
        }

        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->model->count();
        $clients = $this->model->listPaginated($limit, $offset);

        jsonSuccess([
            'clients' => $clients,
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

        if (!hasPermission($payload, 'client', 'read')) {
            jsonError('No tenés permiso para ver clientes', 403);
        }

        $clients = $this->model->listAll();
        jsonSuccess(['clients' => $clients]);
    }

    public function create(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('client_create', 10, 900);

        if (!hasPermission($payload, 'client', 'create')) {
            jsonError('No tenés permiso para crear clientes', 403);
        }

        $data = getJsonInput();

        $name = $data['name'] ?? '';
        if ($name === '') {
            jsonError('El nombre es requerido', 422);
        }

        $name = sanitizeString($name, 255);

        $insertData = ['name' => $name];

        if (!empty($data['email'])) {
            $insertData['email'] = validateEmail($data['email']);
        }
        if (isset($data['phone'])) {
            $insertData['phone'] = sanitizeString($data['phone'], 50);
        }
        if (isset($data['ruc'])) {
            $insertData['ruc'] = validateRuc((string) $data['ruc']);
        }
        if (isset($data['address'])) {
            $insertData['address'] = sanitizeString($data['address'], 500);
        }
        if (isset($data['department'])) {
            $insertData['department'] = sanitizeString($data['department'], 100);
        }
        if (isset($data['notes'])) {
            $insertData['notes'] = sanitizeString($data['notes'], 500);
        }

        $id = $this->model->create($insertData);
        $client = $this->model->findById($id);

        jsonSuccess(['client' => $client], 'Cliente creado', 201);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('client_update', 20, 900);

        if (!hasPermission($payload, 'client', 'edit')) {
            jsonError('No tenés permiso para editar clientes', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            jsonError('ID de cliente inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Cliente no encontrado', 404);
        }

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = sanitizeString($data['name'], 255);
        }
        if (isset($data['email'])) {
            $updateData['email'] = validateEmail($data['email']);
        }
        if (array_key_exists('phone', $data)) {
            $updateData['phone'] = $data['phone'] !== null ? sanitizeString($data['phone'], 50) : null;
        }
        if (array_key_exists('ruc', $data)) {
            $updateData['ruc'] = $data['ruc'] !== null ? validateRuc((string) $data['ruc']) : null;
        }
        if (array_key_exists('address', $data)) {
            $updateData['address'] = $data['address'] !== null ? sanitizeString($data['address'], 500) : null;
        }
        if (array_key_exists('department', $data)) {
            $updateData['department'] = $data['department'] !== null ? sanitizeString($data['department'], 100) : null;
        }
        if (array_key_exists('notes', $data)) {
            $updateData['notes'] = $data['notes'] !== null ? sanitizeString($data['notes'], 500) : null;
        }
        if (array_key_exists('is_active', $data)) {
            $updateData['is_active'] = (int) $data['is_active'];
        }

        if (empty($updateData)) {
            jsonError('No hay datos para actualizar', 422);
        }

        $this->model->update($id, $updateData);
        $updated = $this->model->findById($id);

        jsonSuccess(['client' => $updated], 'Cliente actualizado');
    }

    public function delete(): never
    {
        requireMethod('DELETE');
        $payload = requireAuthMiddleware(true);
        rateLimit('client_delete', 5, 900);

        if (!hasPermission($payload, 'client', 'delete')) {
            jsonError('No tenés permiso para eliminar clientes', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            jsonError('ID de cliente inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Cliente no encontrado', 404);
        }

        $db = \App\Config\Database::getConnection();
        $stmt = $db->prepare('SELECT COUNT(*) FROM package WHERE client_id = :id');
        $stmt->execute(['id' => $id]);
        $packageCount = (int) $stmt->fetchColumn();

        if ($packageCount > 0) {
            jsonError("Este cliente tiene {$packageCount} paquete(s) asociado(s). Desasociá los paquetes antes de eliminar.", 409);
        }

        $this->model->delete($id);
        jsonSuccess(null, 'Cliente eliminado');
    }
}
