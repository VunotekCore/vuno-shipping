<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';

use App\Models\BranchModel;
use App\Config\Database;

requireMethod('DELETE');
$payload = requireAuthMiddleware(true);
rateLimit('branch_delete', 5, 900);

if (!hasPermission($payload, 'branch', 'delete')) {
    jsonError('No tenés permiso para eliminar sucursales', 403);
}

$data = getJsonInput();
$id = (int) ($data['id'] ?? 0);
if ($id <= 0) {
    jsonError('ID inválido', 422);
}

$db = Database::getConnection();
$model = new BranchModel($db);
$existing = $model->findById($id);
if ($existing === null) {
    jsonError('Sucursal no encontrada', 404);
}

$model->delete($id);
jsonSuccess(null, 'Sucursal eliminada');
