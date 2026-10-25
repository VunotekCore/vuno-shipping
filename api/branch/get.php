<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';

use App\Models\BranchModel;
use App\Config\Database;

requireMethod('GET');
$payload = requireAuthMiddleware();

if (!hasPermission($payload, 'branch', 'read')) {
    jsonError('No tenés permiso para ver sucursales', 403);
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    jsonError('ID inválido', 422);
}

$db = Database::getConnection();
$model = new BranchModel($db);
$branch = $model->findById($id);
if ($branch === null) {
    jsonError('Sucursal no encontrada', 404);
}

jsonSuccess(['branch' => $branch]);
