<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\PaymentMethodModel;
use App\Config\Database;

class PaymentMethodController
{
    private PaymentMethodModel $model;

    public function __construct()
    {
        $this->model = new PaymentMethodModel(Database::getConnection());
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'payment_method', 'read')) {
            jsonError('No tenés permiso para ver métodos de pago', 403);
        }

        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total    = $this->model->count();
        $methods  = $this->model->listPaginated($limit, $offset);

        jsonSuccess([
            'methods' => $methods,
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

        if (!hasPermission($payload, 'payment_method', 'read')) {
            jsonError('No tenés permiso para ver métodos de pago', 403);
        }

        jsonSuccess(['methods' => $this->model->listAll()]);
    }

    public function create(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('payment_method_create', 10, 900);

        if (!hasPermission($payload, 'payment_method', 'create')) {
            jsonError('No tenés permiso para crear métodos de pago', 403);
        }

        $data = getJsonInput();
        $name = sanitizeString($data['name'] ?? '', 50);
        if ($name === '') {
            jsonError('El nombre es requerido', 422);
        }

        $id = $this->model->create([
            'name'        => $name,
            'description' => sanitizeString($data['description'] ?? '', 255),
            'is_active'   => (int) ($data['is_active'] ?? 1),
        ]);

        jsonSuccess(['method' => $this->model->findById($id)], 'Método de pago creado', 201);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('payment_method_update', 20, 900);

        if (!hasPermission($payload, 'payment_method', 'edit')) {
            jsonError('No tenés permiso para editar métodos de pago', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            jsonError('ID de método inválido', 422);
        }
        if ($this->model->findById($id) === null) {
            jsonError('Método de pago no encontrado', 404);
        }

        $update = [];
        if (isset($data['name'])) {
            $update['name'] = sanitizeString($data['name'], 50);
        }
        if (array_key_exists('description', $data)) {
            $update['description'] = sanitizeString($data['description'] ?? '', 255);
        }
        if (array_key_exists('is_active', $data)) {
            $update['is_active'] = (int) $data['is_active'];
        }

        $this->model->update($id, $update);
        jsonSuccess(['method' => $this->model->findById($id)], 'Método de pago actualizado');
    }

    public function delete(): never
    {
        requireMethod('DELETE');
        $payload = requireAuthMiddleware(true);
        rateLimit('payment_method_delete', 5, 900);

        if (!hasPermission($payload, 'payment_method', 'delete')) {
            jsonError('No tenés permiso para eliminar métodos de pago', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            jsonError('ID de método inválido', 422);
        }

        $stmt = Database::getConnection()->prepare(
            'SELECT COUNT(*) FROM invoice WHERE payment_method_id = :id'
        );
        $stmt->execute(['id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            jsonError('No se puede eliminar: el método tiene cobros registrados', 409);
        }

        $this->model->delete($id);
        jsonSuccess([], 'Método de pago eliminado');
    }
}