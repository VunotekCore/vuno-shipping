<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';

use App\Services\NotificationService;
use App\Config\Database;

requireMethod('POST');
$payload = requireAuthMiddleware(true);

$data = getJsonInput();
$notificationId = (int) ($data['notification_id'] ?? 0);

if ($notificationId <= 0) {
    jsonError('notification_id requerido', 422);
}

$db = Database::getConnection();
$svc = new NotificationService($db);
$svc->markRead((int) $payload['sub'], $notificationId);

jsonSuccess(null, 'Marcado como leído');
