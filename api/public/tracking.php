<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

requireMethod('GET');

$trackingNumber = trim($_GET['tracking_number'] ?? '');
$token = trim($_GET['token'] ?? '');

if ($trackingNumber === '') {
    jsonError('Número de tracking requerido', 422);
}

if ($token === '') {
    jsonError('Token de acceso requerido', 403);
}

rateLimit('public_tracking', 30, 900);

$db = \App\Config\Database::getConnection();

$stmt = $db->prepare("
    SELECT p.id, p.tracking_number, p.recipient_name, p.recipient_city, p.recipient_department,
           p.recipient_address, p.tracking_token,
           ps.name AS status_name,
           dt.name AS delivery_type_name,
           p.delivery_option,
           ldt.name AS local_delivery_type_name,
           p.received_at, p.delivered_at, p.created_at
    FROM package p
    LEFT JOIN package_status ps ON ps.id = p.status_id
    LEFT JOIN delivery_type dt ON dt.id = p.delivery_type_id
    LEFT JOIN delivery_type ldt ON ldt.id = p.local_delivery_type_id
    WHERE p.tracking_number = :tn LIMIT 1
");
$stmt->execute(['tn' => $trackingNumber]);
$package = $stmt->fetch();

if ($package === null) {
    jsonError('Paquete no encontrado', 404);
}

if (!hash_equals($package['tracking_token'] ?? '', $token)) {
    jsonError('Token inválido', 403);
}

$packageId = (int) $package['id'];
unset($package['id'], $package['tracking_token']);

$eventsStmt = $db->prepare("
    SELECT te.status, te.description, te.location_name, te.latitude, te.longitude,
           te.photo_url, te.recorded_at
    FROM tracking_event te
    WHERE te.package_id = :pid
    ORDER BY te.recorded_at DESC
");
$eventsStmt->execute(['pid' => $packageId]);
$events = $eventsStmt->fetchAll();

jsonSuccess([
    'package' => $package,
    'events' => $events,
]);
