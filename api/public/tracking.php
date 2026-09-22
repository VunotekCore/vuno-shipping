<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

requireMethod('GET');

$trackingNumber = trim($_GET['tracking_number'] ?? '');
$token = trim($_GET['token'] ?? '');

if ($trackingNumber === '') {
    jsonError('Número de tracking requerido', 422);
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
           ss.name AS shipping_service_name,
           dopt.name AS delivery_option_name,
           p.received_at, p.delivered_at, p.created_at
    FROM package p
    LEFT JOIN package_status ps ON ps.id = p.status_id
    LEFT JOIN delivery_type dt ON dt.id = p.delivery_type_id
    LEFT JOIN delivery_type ldt ON ldt.id = p.local_delivery_type_id
    LEFT JOIN shipping_service ss ON ss.id = p.shipping_service_id
    LEFT JOIN delivery_option dopt ON dopt.id = p.delivery_option_id
    WHERE p.tracking_number = :tn LIMIT 1
");
$stmt->execute(['tn' => $trackingNumber]);
$package = $stmt->fetch();

if (!$package) {
    jsonError('Paquete no encontrado', 404);
}

$packageId = (int) $package['id'];

// Token optional: if provided, verify it; if missing, return basic data only
$hasValidToken = false;
if ($token !== '') {
    if (hash_equals($package['tracking_token'] ?? '', $token)) {
        $hasValidToken = true;
    } else {
        jsonError('Token inválido', 403);
    }
}

unset($package['id'], $package['tracking_token']);

if (!$hasValidToken) {
    // Basic data: remove personal info
    unset($package['recipient_name'], $package['recipient_city'],
          $package['recipient_department'], $package['recipient_address']);
}

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
