<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\DocumentSequenceModel;
use App\Config\Database;

class DocumentSequenceController
{
    private DocumentSequenceModel $model;

    public function __construct()
    {
        $this->model = new DocumentSequenceModel(Database::getConnection());
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'document_sequence', 'read')) {
            jsonError('No tenés permiso para ver secuencias', 403);
        }

        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->model->count();
        $sequences = $this->model->listPaginated($limit, $offset);

        jsonSuccess([
            'document_sequences' => $sequences,
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
        rateLimit('document_sequence_create', 10, 900);

        if (!hasPermission($payload, 'document_sequence', 'create')) {
            jsonError('No tenés permiso para crear secuencias', 403);
        }

        $data = getJsonInput();

        $documentCode = sanitizeString($data['document_code'] ?? '', 20);
        $description = isset($data['description']) ? sanitizeString($data['description'], 100) : null;
        $prefix = isset($data['prefix']) ? sanitizeString($data['prefix'], 10) : null;
        $currentNumber = max(1, (int) ($data['current_number'] ?? 1));

        if ($documentCode === '') {
            jsonError('El código del documento es requerido', 422);
        }

        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $documentCode)) {
            jsonError('El código solo puede contener letras mayúsculas, números y guiones bajos', 422);
        }

        $existing = $this->model->findByCode($documentCode);
        if ($existing !== null) {
            jsonError('Ya existe una secuencia con ese código', 409);
        }

        $id = $this->model->create($documentCode, $description, $prefix, $currentNumber);
        $sequence = $this->model->findById($id);

        jsonSuccess(['document_sequence' => $sequence], 'Secuencia creada', 201);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('document_sequence_update', 20, 900);

        if (!hasPermission($payload, 'document_sequence', 'edit')) {
            jsonError('No tenés permiso para editar secuencias', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            jsonError('ID de secuencia inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Secuencia no encontrada', 404);
        }

        if (($existing['document_code'] ?? '') === 'invoice') {
            jsonError('La secuencia de facturas ("invoice") no puede editarse ni renombrarse', 409);
        }

        $updateData = [];

        if (isset($data['document_code'])) {
            $code = sanitizeString($data['document_code'], 20);
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $code)) {
                jsonError('El código solo puede contener letras mayúsculas, números y guiones bajos', 422);
            }
            $conflict = $this->model->findByCode($code);
            if ($conflict !== null && $conflict['id'] !== $id) {
                jsonError('Ya existe una secuencia con ese código', 409);
            }
            $updateData['document_code'] = $code;
        }

        if (array_key_exists('description', $data)) {
            $updateData['description'] = $data['description'] !== null ? sanitizeString($data['description'], 100) : null;
        }

        if (array_key_exists('prefix', $data)) {
            $updateData['prefix'] = $data['prefix'] !== null ? sanitizeString($data['prefix'], 10) : null;
        }

        if (isset($data['current_number'])) {
            $updateData['current_number'] = max(1, (int) $data['current_number']);
        }

        if (empty($updateData)) {
            jsonError('No hay datos para actualizar', 422);
        }

        $this->model->update($id, $updateData);
        $updated = $this->model->findById($id);

        jsonSuccess(['document_sequence' => $updated], 'Secuencia actualizada');
    }

    public function delete(): never
    {
        requireMethod('DELETE');
        $payload = requireAuthMiddleware(true);
        rateLimit('document_sequence_delete', 5, 900);

        if (!hasPermission($payload, 'document_sequence', 'delete')) {
            jsonError('No tenés permiso para eliminar secuencias', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            jsonError('ID de secuencia inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Secuencia no encontrada', 404);
        }

        if (($existing['document_code'] ?? '') === 'invoice') {
            jsonError('La secuencia de facturas ("invoice") no puede eliminarse', 409);
        }

        $this->model->delete($id);
        jsonSuccess(null, 'Secuencia eliminada');
    }
}
