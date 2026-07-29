<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';

use App\Services\NotificationService;
use App\Config\Database;

requireMethod('GET');
$payload = requireAuthMiddleware();

$db = Database::getConnection();
$svc = new NotificationService($db);

$page  = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(50, (int) ($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$notifications = $svc->getList((int) $payload['sub'], $limit, $offset);
$unread = $svc->getUnreadCount((int) $payload['sub']);

jsonSuccess([
    'notifications' => $notifications,
    'unread_count'  => $unread,
    'pagination'    => [
        'page'  => $page,
        'limit' => $limit,
    ],
]);
