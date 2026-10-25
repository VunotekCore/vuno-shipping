<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';

use App\Models\BranchModel;
use App\Config\Database;

requireMethod('PUT');
$payload = requireAuthMiddleware(true);
rateLimit('branch_update', 20, 900);

if (!hasPermission($payload, 'branch', 'edit')) {
    jsonError('No tenés permiso para editar sucursales', 403);
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

$update = [];
if (isset($data['code'])) $update['code'] = sanitizeString($data['code'], 20);
if (isset($data['name'])) $update['name'] = sanitizeString($data['name'], 150);
if (isset($data['country_code'])) $update['country_code'] = sanitizeString($data['country_code'], 2);
if (isset($data['type'])) $update['type'] = ($data['type'] === 'consolidation') ? 'consolidation' : 'direct';
if (array_key_exists('receiving_warehouse_id', $data)) {
    $update['receiving_warehouse_id'] = ($data['receiving_warehouse_id'] === '' || $data['receiving_warehouse_id'] === null) ? null : (int)$data['receiving_warehouse_id'];
}
if (array_key_exists('transfer_to_warehouse_id', $data)) {
    $update['transfer_to_warehouse_id'] = ($data['transfer_to_warehouse_id'] === '' || $data['transfer_to_warehouse_id'] === null) ? null : (int)$data['transfer_to_warehouse_id'];
}
if (isset($data['allows_pickup'])) $update['allows_pickup'] = !empty($data['allows_pickup']) ? 1 : 0;
if (isset($data['is_active'])) $update['is_active'] = ((int)$data['is_active'] === 1) ? 1 : 0;
if (isset($data['sort_order'])) $update['sort_order'] = (int)$data['sort_order'];

if (empty($update)) {
    jsonError('Sin cambios', 422);
}

$model->update($id, $update);
$branch = $model->findById($id);
jsonSuccess(['branch' => $branch], 'Sucursal actualizada');
