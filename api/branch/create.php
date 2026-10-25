<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';

use App\Models\BranchModel;
use App\Config\Database;

requireMethod('POST');
$payload = requireAuthMiddleware(true);
rateLimit('branch_create', 10, 900);

if (!hasPermission($payload, 'branch', 'create')) {
    jsonError('No tenés permiso para crear sucursales', 403);
}

$data = getJsonInput();

if (empty($data['code']) || empty($data['name'])) {
    jsonError('Código y nombre son requeridos', 422);
}

$type = ($data['type'] ?? 'direct') === 'consolidation' ? 'consolidation' : 'direct';
$receiving = isset($data['receiving_warehouse_id']) && $data['receiving_warehouse_id'] !== '' ? (int) $data['receiving_warehouse_id'] : null;
$transfer = isset($data['transfer_to_warehouse_id']) && $data['transfer_to_warehouse_id'] !== '' ? (int) $data['transfer_to_warehouse_id'] : null;

$db = Database::getConnection();
$model = new BranchModel($db);

$insert = [
    'code' => sanitizeString($data['code'], 20),
    'name' => sanitizeString($data['name'], 150),
    'country_code' => isset($data['country_code']) ? sanitizeString($data['country_code'], 2) : 'NI',
    'type' => $type,
    'receiving_warehouse_id' => $receiving,
    'transfer_to_warehouse_id' => $transfer,
    'allows_pickup' => !empty($data['allows_pickup']) ? 1 : 0,
    'is_active' => isset($data['is_active']) ? ((int)$data['is_active'] === 1 ? 1 : 0) : 1,
    'sort_order' => isset($data['sort_order']) ? (int)$data['sort_order'] : 0,
];

$id = $model->create($insert);
$branch = $model->findById($id);
jsonSuccess(['branch' => $branch], 'Sucursal creada', 201);
