<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\PackageModel;
use App\Models\TrackingEventModel;
use App\Models\DocumentSequenceModel;
use App\Services\PricingService;
use App\Config\Database;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;

class PackageController
{
    private PackageModel $model;

    public function __construct()
    {
        $this->model = new PackageModel(Database::getConnection());
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'package', 'read')) {
            jsonError('No tenés permiso para ver paquetes', 403);
        }

        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $status   = $_GET['status'] ?? null;
        $clientId = isset($_GET['client_id']) ? (int) $_GET['client_id'] : null;
        $driverId = isset($_GET['driver_id']) ? (int) $_GET['driver_id'] : null;

        $result = $this->model->listPaginated($limit, $offset, $status, $clientId, $driverId);

        jsonSuccess([
            'packages' => $result['packages'],
            'pagination' => [
                'page'  => $page,
                'limit' => $limit,
                'total' => $result['total'],
                'pages' => (int) ceil($result['total'] / $limit),
            ],
        ]);
    }

    public function get(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'package', 'read')) {
            jsonError('No tenés permiso para ver paquetes', 403);
        }

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            jsonError('ID de paquete inválido', 422);
        }

        $package = $this->model->findById($id);
        if ($package === null) {
            jsonError('Paquete no encontrado', 404);
        }

        $trackingModel = new TrackingEventModel(Database::getConnection());
        $events = $trackingModel->listByPackage($id);

        jsonSuccess(['package' => $package, 'events' => $events]);
    }

    public function create(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('package_create', 10, 900);

        if (!hasPermission($payload, 'package', 'create')) {
            jsonError('No tenés permiso para crear paquetes', 403);
        }

        $data = getJsonInput();

        $senderName = $data['sender_name'] ?? '';
        $recipientName = $data['recipient_name'] ?? '';
        $recipientPhone = $data['recipient_phone'] ?? '';
        $recipientAddress = $data['recipient_address'] ?? '';

        if ($senderName === '' || $recipientName === '' || $recipientPhone === '' || $recipientAddress === '') {
            jsonError('Nombre remitente, nombre destinatario, teléfono y dirección son requeridos', 422);
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $seqModel = new DocumentSequenceModel($db);
            $trackingNumber = $seqModel->nextNumber('tracking');
            $trackingToken = bin2hex(random_bytes(32));

            $insertData = [
                'tracking_number' => $trackingNumber,
                'tracking_token' => $trackingToken,
                'sender_name' => sanitizeString($senderName, 255),
                'sender_phone' => sanitizeString($data['sender_phone'] ?? '', 50),
                'recipient_name' => sanitizeString($recipientName, 255),
                'recipient_phone' => sanitizeString($recipientPhone, 50),
                'recipient_address' => sanitizeString($recipientAddress, 500),
                'created_by' => (int) $payload['sub'],
            ];

            if (isset($data['client_id'])) {
                $clientModel = new \App\Models\ClientModel($db);
                $client = $clientModel->findById((int) $data['client_id']);
                if ($client === null) {
                    $db->rollBack();
                    jsonError('El cliente seleccionado no existe', 422);
                }
                $insertData['client_id'] = (int) $data['client_id'];
            }

            if (isset($data['recipient_city'])) {
                $insertData['recipient_city'] = sanitizeString($data['recipient_city'], 100);
            }
            if (isset($data['recipient_department'])) {
                $insertData['recipient_department'] = sanitizeString($data['recipient_department'], 100);
            }
            if (isset($data['zone_id'])) {
                $insertData['zone_id'] = (int) $data['zone_id'];
            }
            if (isset($data['description'])) {
                $insertData['description'] = sanitizeString($data['description'], 500);
            }
            if (isset($data['weight'])) {
                $insertData['weight'] = (float) $data['weight'];
            }
            if (isset($data['length'])) {
                $insertData['length'] = (float) $data['length'];
            }
            if (isset($data['width'])) {
                $insertData['width'] = (float) $data['width'];
            }
            if (isset($data['height'])) {
                $insertData['height'] = (float) $data['height'];
            }
            if (isset($data['declared_value'])) {
                $insertData['declared_value'] = (float) $data['declared_value'];
            }
            if (isset($data['notes'])) {
                $insertData['notes'] = sanitizeString($data['notes'], 500);
            }

            $deliveryType = $data['delivery_type'] ?? 'aereo';
            $deliveryTypeId = $this->model->getDeliveryTypeId($deliveryType);
            if ($deliveryTypeId !== null) {
                $insertData['delivery_type_id'] = $deliveryTypeId;
            }

            $insertData['delivery_option'] = $data['delivery_option'] ?? 'branch';
            if (isset($data['local_delivery_type'])) {
                $localDtId = $this->model->getDeliveryTypeId($data['local_delivery_type']);
                if ($localDtId !== null) {
                    $insertData['local_delivery_type_id'] = $localDtId;
                }
            }

            if (isset($data['shipping_service'])) {
                $ssId = $this->model->getShippingServiceId($data['shipping_service']);
                if ($ssId !== null) {
                    $insertData['shipping_service_id'] = $ssId;
                }
            }

            if (isset($data['delivery_option'])) {
                $doptId = $this->model->getDeliveryOptionId($data['delivery_option']);
                if ($doptId !== null) {
                    $insertData['delivery_option_id'] = $doptId;
                }
            }

            $statusId = $this->model->getStatusId('pending');
            if ($statusId !== null) {
                $insertData['status_id'] = $statusId;
            }

            $pricingService = new PricingService();

            $zoneId = isset($data['zone_id']) ? (int) $data['zone_id'] : null;
            $zone = null;
            if ($zoneId !== null) {
                $zoneStmt = $db->prepare('SELECT * FROM zone WHERE id = :id LIMIT 1');
                $zoneStmt->execute(['id' => $zoneId]);
                $zoneRow = $zoneStmt->fetch();
                $zone = $zoneRow ?: null;
            }

            $ruleModel = new \App\Models\PricingRuleModel($db);
            $deliveryTypeName = $deliveryType;

            // Use explicit rule if provided, otherwise auto-detect
            $rule = null;
            if (isset($data['pricing_rule_id'])) {
                $rule = $ruleModel->findById((int) $data['pricing_rule_id']);
            }
            if ($rule === null) {
                $rule = $ruleModel->findBestMatch($zoneId, $deliveryTypeName, null);
            }

            $pricing = [
                'volumetric_weight' => 0,
                'chargeable_weight' => 0,
                'flete_cost' => 0,
                'delivery_cost' => 0,
                'total_amount' => 0,
            ];

            if ($rule !== null) {
                $pricing = $pricingService->calculatePackageTotal($insertData, $rule, $zone);
                $insertData['volumetric_weight'] = $pricing['volumetric_weight'];
                $insertData['chargeable_weight'] = $pricing['chargeable_weight'];
                $insertData['flete_cost'] = $pricing['flete_cost'];
                $insertData['delivery_cost'] = $pricing['delivery_cost'];
                $insertData['shipping_cost'] = $pricing['total_amount'];
                $insertData['pricing_rule_id'] = (int) $rule['id'];
            } else {
                $insertData['volumetric_weight'] = 0;
                $insertData['chargeable_weight'] = 0;
                $insertData['flete_cost'] = 0;
                $insertData['delivery_cost'] = 0;
                $insertData['shipping_cost'] = 0;
            }

            $packageId = $this->model->create($insertData);

            $trackingModel = new TrackingEventModel($db);
            $trackingModel->create([
                'package_id' => $packageId,
                'status' => 'pending',
                'description' => 'Paquete registrado en el sistema',
                'recorded_at' => date('Y-m-d H:i:s'),
                'created_by' => (int) $payload['sub'],
            ]);

            $db->commit();

            $package = $this->model->findById($packageId);
            jsonSuccess(['package' => $package, 'pricing' => $pricing], 'Paquete creado', 201);
        } catch (\Throwable $e) {
            $db->rollBack();
            apiLog('ERROR', 'Error creating package: ' . $e->getMessage());
            jsonError('Error al crear el paquete', 500);
        }
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('package_update', 20, 900);

        if (!hasPermission($payload, 'package', 'edit')) {
            jsonError('No tenés permiso para editar paquetes', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            jsonError('ID de paquete inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Paquete no encontrado', 404);
        }

        $updateData = [];

        if (isset($data['status_id'])) {
            jsonError('Para cambiar el estado, usá el endpoint updateStatus', 422);
        }

        if (isset($data['client_id'])) {
            $updateData['client_id'] = (int) $data['client_id'];
        }
        if (isset($data['sender_name'])) {
            $updateData['sender_name'] = sanitizeString($data['sender_name'], 255);
        }
        if (isset($data['sender_phone'])) {
            $updateData['sender_phone'] = sanitizeString($data['sender_phone'], 50);
        }
        if (isset($data['recipient_name'])) {
            $updateData['recipient_name'] = sanitizeString($data['recipient_name'], 255);
        }
        if (isset($data['recipient_phone'])) {
            $updateData['recipient_phone'] = sanitizeString($data['recipient_phone'], 50);
        }
        if (isset($data['recipient_address'])) {
            $updateData['recipient_address'] = sanitizeString($data['recipient_address'], 500);
        }
        if (isset($data['recipient_city'])) {
            $updateData['recipient_city'] = sanitizeString($data['recipient_city'], 100);
        }
        if (isset($data['recipient_department'])) {
            $updateData['recipient_department'] = sanitizeString($data['recipient_department'], 100);
        }
        if (isset($data['description'])) {
            $updateData['description'] = sanitizeString($data['description'], 500);
        }
        if (isset($data['weight'])) {
            $updateData['weight'] = (float) $data['weight'];
        }
        if (isset($data['length'])) {
            $updateData['length'] = (float) $data['length'];
        }
        if (isset($data['width'])) {
            $updateData['width'] = (float) $data['width'];
        }
        if (isset($data['height'])) {
            $updateData['height'] = (float) $data['height'];
        }
        if (isset($data['declared_value'])) {
            $updateData['declared_value'] = (float) $data['declared_value'];
        }
        if (isset($data['notes'])) {
            $updateData['notes'] = sanitizeString($data['notes'], 500);
        }
        if (isset($data['delivery_type'])) {
            $deliveryTypeId = $this->model->getDeliveryTypeId($data['delivery_type']);
            if ($deliveryTypeId !== null) {
                $updateData['delivery_type_id'] = $deliveryTypeId;
            }
        }
        if (isset($data['delivery_option'])) {
            $updateData['delivery_option'] = $data['delivery_option'];
        }
        if (isset($data['local_delivery_type'])) {
            $localDtId = $this->model->getDeliveryTypeId($data['local_delivery_type']);
            if ($localDtId !== null) {
                $updateData['local_delivery_type_id'] = $localDtId;
            }
        }
        if (isset($data['shipping_service'])) {
            $ssId = $this->model->getShippingServiceId($data['shipping_service']);
            if ($ssId !== null) {
                $updateData['shipping_service_id'] = $ssId;
            }
        }
        if (isset($data['delivery_option'])) {
            $doptId = $this->model->getDeliveryOptionId($data['delivery_option']);
            if ($doptId !== null) {
                $updateData['delivery_option_id'] = $doptId;
            }
        }

        if (empty($updateData)) {
            jsonError('No hay datos para actualizar', 422);
        }

        $this->model->update($id, $updateData);
        $updated = $this->model->findById($id);

        jsonSuccess(['package' => $updated], 'Paquete actualizado');
    }

    public function delete(): never
    {
        requireMethod('DELETE');
        $payload = requireAuthMiddleware(true);
        rateLimit('package_delete', 5, 900);

        if (!hasPermission($payload, 'package', 'delete')) {
            jsonError('No tenés permiso para eliminar paquetes', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            jsonError('ID de paquete inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Paquete no encontrado', 404);
        }

        $statusName = $this->model->getStatusName((int) $existing['status_id']);
        if ($statusName !== null && $statusName !== 'pending') {
            jsonError('Solo se pueden eliminar paquetes en estado pendiente', 409);
        }

        $this->model->delete($id);
        jsonSuccess(null, 'Paquete eliminado');
    }

    public function assign(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('package_assign', 10, 900);

        if (!hasPermission($payload, 'package', 'edit')) {
            jsonError('No tenés permiso para asignar paquetes', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);
        $driverId = isset($data['assigned_to']) ? (int) $data['assigned_to'] : null;

        if ($id <= 0) {
            jsonError('ID de paquete inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Paquete no encontrado', 404);
        }

        $this->model->update($id, ['assigned_to' => $driverId]);
        $updated = $this->model->findById($id);

        jsonSuccess(['package' => $updated], 'Repartidor asignado');
    }

    public function updateStatus(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('package_status', 10, 900);

        if (!hasPermission($payload, 'package', 'edit')) {
            jsonError('No tenés permiso para cambiar estado de paquetes', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);
        $newStatus = $data['status'] ?? '';

        if ($id <= 0 || $newStatus === '') {
            jsonError('ID y status son requeridos', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Paquete no encontrado', 404);
        }

        $currentStatusId = (int) $existing['status_id'];
        $currentStatusName = $this->model->getStatusName($currentStatusId);

        if ($currentStatusName === null) {
            jsonError('Estado actual del paquete inválido', 422);
        }

        if (!$this->model->isValidTransition($currentStatusName, $newStatus)) {
            jsonError("No se puede cambiar de '{$currentStatusName}' a '{$newStatus}'", 409);
        }

        $newStatusId = $this->model->getStatusId($newStatus);
        if ($newStatusId === null) {
            jsonError('Estado inválido', 422);
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $updateData = ['status_id' => $newStatusId];

            if ($newStatus === 'received') {
                $updateData['received_at'] = date('Y-m-d H:i:s');
            } elseif ($newStatus === 'delivered') {
                $updateData['delivered_at'] = date('Y-m-d H:i:s');
            }

            $this->model->update($id, $updateData);

            $trackingModel = new TrackingEventModel($db);
            $trackingModel->create([
                'package_id' => $id,
                'status' => $newStatus,
                'description' => $data['description'] ?? null,
                'latitude' => isset($data['latitude']) ? (float) $data['latitude'] : null,
                'longitude' => isset($data['longitude']) ? (float) $data['longitude'] : null,
                'location_name' => $data['location_name'] ?? null,
                'photo_url' => $data['photo_url'] ?? null,
                'recorded_at' => date('Y-m-d H:i:s'),
                'created_by' => (int) $payload['sub'],
            ]);

            $db->commit();

            $updated = $this->model->findById($id);
            jsonSuccess(['package' => $updated], 'Estado actualizado');
        } catch (\Throwable $e) {
            $db->rollBack();
            apiLog('ERROR', 'Error updating package status: ' . $e->getMessage());
            jsonError('Error al actualizar el estado', 500);
        }
    }

    public function qrcode(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'package', 'read')) {
            jsonError('No tenés permiso para ver paquetes', 403);
        }

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            jsonError('ID de paquete inválido', 422);
        }

        $package = $this->model->findById($id);
        if ($package === null) {
            jsonError('Paquete no encontrado', 404);
        }

        $baseUrl = config()['BASE_URL'] ?? 'https://' . ($_SERVER['HTTP_HOST'] ?? 'shipping.vunotek.com');
        $trackingUrl = rtrim($baseUrl, '/') . '/rastreo?tracking=' . rawurlencode($package['tracking_number']);
        if (!empty($package['tracking_token'])) {
            $trackingUrl .= '&token=' . rawurlencode($package['tracking_token']);
        }

        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'eccLevel' => EccLevel::M,
            'scale' => 10,
            'outputBase64' => false,
            'bgColor' => [255, 255, 255],
            'imageTransparent' => false,
        ]);

        $qrcode = new QRCode($options);

        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=86400');
        header('X-Content-Type-Options: nosniff');

        echo $qrcode->render($trackingUrl);
        exit;
    }

    public function label(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'package', 'read')) {
            jsonError('No tenés permiso para ver paquetes', 403);
        }

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            jsonError('ID de paquete inválido', 422);
        }

        $package = $this->model->findById($id);
        if ($package === null) {
            jsonError('Paquete no encontrado', 404);
        }

        $baseUrl = config()['BASE_URL'] ?? 'https://' . ($_SERVER['HTTP_HOST'] ?? 'shipping.vunotek.com');
        $trackingUrl = rtrim($baseUrl, '/') . '/rastreo?tracking=' . rawurlencode($package['tracking_number']);
        if (!empty($package['tracking_token'])) {
            $trackingUrl .= '&token=' . rawurlencode($package['tracking_token']);
        }

        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'eccLevel' => EccLevel::M,
            'scale' => 6,
            'outputBase64' => true,
            'bgColor' => [255, 255, 255],
            'imageTransparent' => false,
        ]);

        $qrcode = new QRCode($options);
        $qrDataUri = $qrcode->render($trackingUrl);

        $weight = ($package['chargeable_weight'] ?? $package['weight']) 
            ? number_format((float) ($package['chargeable_weight'] ?? $package['weight']), 2) . ' lb' 
            : '—';

        $senderName = htmlspecialchars($package['sender_name'] ?? '', ENT_QUOTES, 'UTF-8');
        $recipientName = htmlspecialchars($package['recipient_name'] ?? '', ENT_QUOTES, 'UTF-8');
        $trackingNumber = htmlspecialchars($package['tracking_number'] ?? '', ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($package['description'] ?? '', ENT_QUOTES, 'UTF-8');

        header('Content-Type: text/html; charset=UTF-8');
        echo <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Etiqueta - {$trackingNumber}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f5f5f5; font-family: Arial, Helvetica, sans-serif; }
  .label { width: 360px; min-height: 540px; background: #fff; border: 1px solid #ccc; padding: 16px; display: flex; flex-direction: column; justify-content: space-between; }
  .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 8px; }
  .header h1 { font-size: 16px; font-weight: bold; text-transform: uppercase; }
  .tracking { text-align: center; margin: 8px 0; }
  .tracking .number { font-size: 22px; font-weight: bold; letter-spacing: 2px; word-break: break-all; }
  .qr { text-align: center; margin: 8px 0; }
  .qr img { width: 120px; height: 120px; }
  .info { border-top: 1px solid #ccc; padding-top: 8px; }
  .info-row { display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 4px; }
  .info-row .label-tag { font-weight: bold; color: #555; }
  .recipient { border-top: 1px solid #ccc; padding-top: 8px; margin-top: 8px; }
  .recipient .name { font-size: 14px; font-weight: bold; }
  .recipient .detail { font-size: 11px; color: #555; }
  .footer { text-align: center; font-size: 9px; color: #999; border-top: 1px solid #ccc; padding-top: 8px; margin-top: 8px; }
  @media print { body { background: #fff; } .label { border: none; } }
</style>
</head>
<body>
<div class="label">
  <div>
    <div class="header">
      <h1>VUNO WEB SHIPPING</h1>
    </div>
    <div class="tracking">
      <div class="number">{$trackingNumber}</div>
    </div>
    <div class="qr">
      <img src="{$qrDataUri}" alt="QR" width="120" height="120">
    </div>
    <div class="info">
      <div class="info-row"><span class="label-tag">Peso:</span><span>{$weight}</span></div>
      <div class="info-row"><span class="label-tag">Contenido:</span><span>{$description}</span></div>
    </div>
  </div>
  <div>
    <div class="recipient">
      <div class="name">{$recipientName}</div>
    </div>
    <div class="footer">
      Rastreo: {$trackingUrl} | Vuno Web Shipping
    </div>
  </div>
</div>
<script>window.print()</script>
</body>
</html>
HTML;
        exit;
    }
}
