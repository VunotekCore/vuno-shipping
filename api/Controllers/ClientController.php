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

    private function isVendor(array $payload): bool
    {
        return ($payload['role_slug'] ?? '') === 'vendedor';
    }

    private function agentScope(array $payload): ?int
    {
        return $this->isVendor($payload) ? (int) $payload['sub'] : null;
    }

    private function assertOwnership(array $payload, array $client): void
    {
        if ($this->isVendor($payload) && (int) ($client['agent_id'] ?? 0) !== (int) $payload['sub']) {
            jsonError('No tenés permiso sobre este cliente', 403);
        }
    }

    private function validateAgent(array $data): ?int
    {
        if (!isset($data['agent_id']) || $data['agent_id'] === '' || $data['agent_id'] === null) {
            return null;
        }
        $agentId = (int) $data['agent_id'];
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT COUNT(*) FROM `user` WHERE id = :id');
        $stmt->execute(['id' => $agentId]);
        if ((int) $stmt->fetchColumn() === 0) {
            jsonError('El agente seleccionado no existe', 422);
        }
        return $agentId;
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

        $agentId = $this->agentScope($payload);
        if ($agentId === null && isset($_GET['agent_id']) && $_GET['agent_id'] !== '') {
            $agentId = (int) $_GET['agent_id'];
        }

        $total = $this->model->count($agentId);
        $clients = $this->model->listPaginated($limit, $offset, $agentId);

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

        $clients = $this->model->listAll($this->agentScope($payload));
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

        if (array_key_exists('company', $data)) {
            $insertData['company'] = $data['company'] !== null && $data['company'] !== '' ? sanitizeString($data['company'], 150) : null;
        }
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

        if ($this->isVendor($payload)) {
            $insertData['agent_id'] = (int) $payload['sub'];
        } else {
            $agentId = $this->validateAgent($data);
            if ($agentId !== null) {
                $insertData['agent_id'] = $agentId;
            }
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

        $this->assertOwnership($payload, $existing);

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = sanitizeString($data['name'], 255);
        }
        if (array_key_exists('company', $data)) {
            $updateData['company'] = ($data['company'] !== null && $data['company'] !== '') ? sanitizeString($data['company'], 150) : null;
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

        if (!$this->isVendor($payload) && array_key_exists('agent_id', $data)) {
            $updateData['agent_id'] = $this->validateAgent($data);
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

        $this->assertOwnership($payload, $existing);

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
