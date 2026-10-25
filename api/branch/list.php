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

$db = Database::getConnection();
$model = new BranchModel($db);

$activeOnly = null;
if (isset($_GET['active'])) {
    $activeOnly = $_GET['active'] === '1' || strtolower((string)$_GET['active']) === 'true';
}

$branches = $model->listAll($activeOnly);
jsonSuccess(['branches' => $branches]);
