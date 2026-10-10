<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Services\PricingService;
use App\Models\PricingRuleModel;
use App\Config\Database;

requireMethod('POST');

$payload = requireAuthMiddleware();

if (!hasPermission($payload, 'package', 'create') && !hasPermission($payload, 'quote', 'create')) {
    jsonError('No tenés permiso para calcular precios', 403);
}

$data = getJsonInput();

$weight = (float)($data['weight'] ?? 0);
$length = (float)($data['length'] ?? 0);
$width  = (float)($data['width'] ?? 0);
$height = (float)($data['height'] ?? 0);
$declaredValue = (float)($data['declared_value'] ?? 0);
$deliveryTypeId = (int)($data['delivery_type_id'] ?? 0);
$shippingServiceId = isset($data['shipping_service_id']) ? (int) $data['shipping_service_id'] : null;
$deliveryOption = $data['delivery_option'] ?? 'branch';
$zoneId = isset($data['zone_id']) ? (int) $data['zone_id'] : null;
$pricingRuleId = isset($data['pricing_rule_id']) ? (int) $data['pricing_rule_id'] : null;

// Load a specific rule or find best match
$db = Database::getConnection();
$ruleModel = new PricingRuleModel($db);
$rule = null;
if ($pricingRuleId !== null) {
    $rule = $ruleModel->findById($pricingRuleId);
} elseif ($deliveryTypeId > 0) {
    $dtStmt = $db->prepare('SELECT name FROM delivery_type WHERE id = :id LIMIT 1');
    $dtStmt->execute(['id' => $deliveryTypeId]);
    $dtRow = $dtStmt->fetch();
    $deliveryType = $dtRow ? $dtRow['name'] : 'door';
    $rule = $ruleModel->findBestMatch($zoneId, $deliveryType, null);
} elseif ($shippingServiceId !== null && $shippingServiceId > 0) {
    $ssStmt = $db->prepare('SELECT name FROM shipping_service WHERE id = :id LIMIT 1');
    $ssStmt->execute(['id' => $shippingServiceId]);
    $ssRow = $ssStmt->fetch();
    $deliveryType = $ssRow ? $ssRow['name'] : 'aereo';
    $rule = $ruleModel->findBestMatch($zoneId, $deliveryType, null);
}

if ($rule === null) {
    jsonError('No se encontró una regla de precios para los parámetros dados', 404);
}

// Validate weight or dimensions only for types that need them
$typeName = $rule['type_name'] ?? 'weight';
$needsWeightOrDims = $typeName === 'weight' || $typeName === 'volume';
if ($needsWeightOrDims && $weight <= 0 && ($length <= 0 || $width <= 0 || $height <= 0)) {
    jsonError('Debe proporcionar peso o dimensiones para cotizar', 422);
}

// Load zone if specified
$zone = null;
if ($zoneId !== null) {
    $stmt = $db->prepare('SELECT * FROM zone WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $zoneId]);
    $zoneRow = $stmt->fetch();
    $zone = $zoneRow ?: null;
}

$pricingService = new PricingService();
$result = $pricingService->calculatePackageTotal([
    'weight' => $weight,
    'length' => $length,
    'width' => $width,
    'height' => $height,
    'declared_value' => $declaredValue,
    'delivery_type_id' => $deliveryTypeId,
    'delivery_option' => $deliveryOption,
], $rule, $zone);

$result['rule_id'] = (int) $rule['id'];
$result['rule_name'] = $rule['name'];

jsonSuccess(['pricing' => $result]);
