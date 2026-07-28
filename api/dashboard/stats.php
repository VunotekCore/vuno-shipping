<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

requireMethod('GET');
$payload = requireAuthMiddleware();

if (!hasPermission($payload, 'package', 'read')) {
    jsonError('No tenés permiso', 403);
}

$db = \App\Config\Database::getConnection();

$packageModel = new \App\Models\PackageModel($db);
$clientModel = new \App\Models\ClientModel($db);

$totalPackages = $packageModel->count();
$activePackages = $packageModel->countActive();
$deliveredToday = $packageModel->countDeliveredToday();
$totalClients = $clientModel->count();

$transitCount = $packageModel->countByStatus('in_transit');
$pendingCount = $packageModel->countByStatus('pending');
$deliveredCount = $packageModel->countByStatus('delivered');

jsonSuccess([
    'stats' => [
        'total_packages' => $totalPackages,
        'active_packages' => $activePackages,
        'delivered_today' => $deliveredToday,
        'total_clients' => $totalClients,
        'in_transit' => $transitCount,
        'pending' => $pendingCount,
        'delivered' => $deliveredCount,
    ],
]);
