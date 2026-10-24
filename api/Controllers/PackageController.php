<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\PackageModel;
use App\Models\TrackingEventModel;
use App\Services\PackageService;
use App\Services\PackageNotifierService;
use App\Services\PackageStorageService;
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
        $trackingNumber = $_GET['tracking'] ?? '';

        if ($id > 0) {
            $package = $this->model->findById($id);
        } elseif ($trackingNumber !== '') {
            $package = $this->model->findByTrackingNumber($trackingNumber);
        } else {
            jsonError('ID o tracking number requerido', 422);
        }

        if ($package === null) {
            jsonError('Paquete no encontrado', 404);
        }

        $pkgId = (int) $package['id'];
        $trackingModel = new TrackingEventModel(Database::getConnection());
        $events = $trackingModel->listByPackage($pkgId);

        if (isset($package['client_company'])) {
            $package['client'] = [
                'id' => isset($package['client_id']) ? (int) $package['client_id'] : null,
                'name' => $package['client_name'] ?? null,
                'company' => $package['client_company'],
                'email' => $package['client_email'] ?? null,
                'phone' => $package['client_phone'] ?? null,
            ];
        }

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

        $service = new PackageService(Database::getConnection());
        $result = $service->create($data, $payload);

        jsonSuccess(
            ['package' => $result['package'], 'pricing' => $result['pricing'], 'invoice' => $result['invoice']],
            'Paquete creado',
            201
        );
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('package_update', 20, 900);

        if (!hasPermission($payload, 'package', 'edit')) {
            jsonError('No tenés permiso para editar paquetes', 403);
        }

        // Edición de datos de paquete DESHABILITADA (410 Gone).
        //
        // La dirección del destinatario no es un dato suelto: alimenta el punto de
        // entrega del despacho. RouteStopModel::generateFromPackages() congela
        // nombre, dirección y coordenadas en route_stop al generar las paradas, y
        // el paquete además puede estar asignado a un embarque (shipment_package)
        // o a un run. Editar sin saber en cuál de esos estados está deja dos
        // versiones de la verdad: el stop que ve el conductor con un dato y el
        // paquete con otro.
        //
        // "Borrar y readmitir" tampoco es una salida: delete() se lleva en cascada
        // la factura y todo el historial de tracking (invoice y tracking_event son
        // ON DELETE CASCADE en schema.sql), y además saca el paquete del embarque
        // sin aviso.
        //
        // Se rehabilita cuando exista una regla de estado explícita (por ejemplo,
        // solo mientras no tenga paradas generadas y no esté en un embarque).
        jsonError(
            'La edición de paquetes está deshabilitada. No hay forma segura de corregir un paquete ya admitido: '
            . 'editarlo puede pisar el punto de entrega de un despacho, y eliminarlo borra su factura y su historial. '
            . 'Reporta el caso para resolverlo manualmente.',
            410
        );
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
        if ($statusName !== null && !in_array($statusName, ['pending', 'received', 'in_nicaragua'], true)) {
            jsonError('Solo se pueden eliminar paquetes pendientes, recibidos en Miami o recibidos en Nicaragua', 409);
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

            try {
                $storageService = new PackageStorageService($db);
                if ($newStatus === 'in_nicaragua') {
                    $storageService->free($id, (int) $payload['sub'], 'released', 'Despachado de bodega Miami');
                    $storageService->assign($id, 'local', null, null, (int) $payload['sub'], 'stored', 'Ubicado en bodega Nicaragua');
                } elseif (in_array($newStatus, ['returned', 'cancelled', 'delivered'], true)) {
                    $storageService->free($id, (int) $payload['sub'], 'released', 'Salida de bodega (' . $newStatus . ')');
                }
            } catch (\Throwable $storageError) {
                apiLog('ERROR', 'Error gestionando almacen del paquete ' . $id . ': ' . $storageError->getMessage());
            }

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

            $notifier = new PackageNotifierService($db);
            $notifier->notifyStatusChange($updated, $currentStatusName, $newStatus, $data['description'] ?? null);

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

        $baseUrl = (config()['app']['base_url'] ?? 'http://localhost:4321');
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

        $baseUrl = (config()['app']['base_url'] ?? 'http://localhost:4321');
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
        ]);

        $locationCode = htmlspecialchars((string) ($package['storage_location_code'] ?? '—'), ENT_QUOTES, 'UTF-8');
        $storageCategory = htmlspecialchars((string) ($package['storage_category_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $storageLabel = $locationCode !== '—' ? ($storageCategory !== '' ? $locationCode . ' · ' . $storageCategory : $locationCode) : '—';


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
      <div class="info-row"><span class="label-tag">Ubicación:</span><span>{$storageLabel}</span></div>
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
