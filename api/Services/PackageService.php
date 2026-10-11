<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use App\Exceptions\ApiException;
use App\Models\ClientModel;
use App\Models\DocumentSequenceModel;
use App\Models\InvoiceModel;
use App\Models\PackageModel;
use App\Models\PricingRuleModel;
use App\Models\TrackingEventModel;

/**
 * Crea paquetes completos (tracking, pricing, evento de tracking, factura y
 * notificación). Extraído de PackageController::create() para poder reusarlo
 * desde la conversión de cotizaciones (QuoteController::convert).
 *
 * `$data` mantiene el mismo shape que el endpoint de admisión, de modo que
 * Admisión y Cotización comparten exactamente la misma lógica de negocio.
 */
class PackageService
{
    public function __construct(private PDO $db) {}

    /**
     * @param array $data    Datos de admisión (sender/recipient/medidas/servicio/origen...).
     * @param array $payload JWT payload (sub = created_by).
     * @param array|null $pricingOverride Si se provee, se usa en vez de calcular
     *                                    el precio (flete/delivery/total) — usado
     *                                    por la conversión de cotización para
     *                                    respetar el precio prometido.
     * @param array{notify?: bool, useTransaction?: bool} $options
     *        useTransaction=false permite que el llamador controle la
     *        transacción (ej: convertir una cotización con varios ítems de
     *        forma atómica).
     * @return array{package: array, pricing: array, invoice: ?array}
     */
    public function create(array $data, array $payload, ?array $pricingOverride = null, array $options = []): array
    {
        $notify = $options['notify'] ?? true;
        $ownTx = $options['useTransaction'] ?? true;
        $model = new PackageModel($this->db);

        if ($ownTx) {
            $this->db->beginTransaction();
        }

        try {
            $senderName = $data['sender_name'] ?? '';
            $recipientName = $data['recipient_name'] ?? '';
            $recipientPhone = $data['recipient_phone'] ?? '';
            $recipientAddress = $data['recipient_address'] ?? '';

            if ($senderName === '' || $recipientName === '' || $recipientPhone === '' || $recipientAddress === '') {
                throw new ApiException(422, 'Nombre remitente, nombre destinatario, teléfono y dirección son requeridos');
            }

            $seqModel = new DocumentSequenceModel($this->db);
            $seq = $seqModel->findByCode('tracking');
            if ($seq === null) {
                throw new ApiException(500, 'Secuencia de tracking no configurada');
            }
            $prefix = $seq['prefix'] ?? 'EV';

            do {
                $trackingNumber = self::generateRandomTrackingCode($prefix);
            } while ($model->findByTrackingNumber($trackingNumber) !== null);

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

            $client = null;
            if (isset($data['client_id'])) {
                $clientModel = new ClientModel($this->db);
                $client = $clientModel->findById((int) $data['client_id']);
                if ($client === null) {
                    throw new ApiException(422, 'El cliente seleccionado no existe');
                }
                $insertData['client_id'] = (int) $data['client_id'];
            }

            if (isset($data['quote_id'])) {
                $insertData['quote_id'] = (int) $data['quote_id'];
            }
            if (!empty($data['pickup_required'])) {
                $insertData['pickup_required'] = 1;
            }
            if (isset($data['pickup_vehicle_id']) && $data['pickup_vehicle_id'] !== '') {
                $insertData['pickup_vehicle_id'] = (int) $data['pickup_vehicle_id'];
            }

            if (isset($data['recipient_city'])) {
                $insertData['recipient_city'] = sanitizeString($data['recipient_city'], 100);
            }
            if (isset($data['recipient_department'])) {
                $insertData['recipient_department'] = sanitizeString($data['recipient_department'], 100);
            }
            if (isset($data['latitude']) && $data['latitude'] !== null && $data['latitude'] !== '') {
                $lat = (float) $data['latitude'];
                if ($lat < -90 || $lat > 90) {
                    throw new ApiException(422, 'Latitud inválida (rango -90 a 90)');
                }
                $insertData['latitude'] = $lat;
            }
            if (isset($data['longitude']) && $data['longitude'] !== null && $data['longitude'] !== '') {
                $lng = (float) $data['longitude'];
                if ($lng < -180 || $lng > 180) {
                    throw new ApiException(422, 'Longitud inválida (rango -180 a 180)');
                }
                $insertData['longitude'] = $lng;
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

            $shippingServiceName = isset($data['shipping_service']) ? trim((string) $data['shipping_service']) : '';
            $explicitDeliveryType = isset($data['delivery_type']) && $data['delivery_type'] !== '' ? (string) $data['delivery_type'] : '';

            // delivery_type_id se deriva del servicio de envio (ej: Marítimo ->
            // maritimo), luego del tipo explicito y por ultimo de la regla de
            // pricing. Sin fallback fijo a 'aereo' (evita el desajuste previo).
            $deliveryTypeId = null;
            if ($shippingServiceName !== '') {
                $deliveryTypeId = $model->getDeliveryTypeIdByServiceName($shippingServiceName);
            }
            if ($deliveryTypeId === null && $explicitDeliveryType !== '') {
                $deliveryTypeId = $model->getDeliveryTypeId($explicitDeliveryType);
            }
            if ($deliveryTypeId === null && isset($data['pricing_rule_id'])) {
                $ruleRow = (new PricingRuleModel($this->db))->findById((int) $data['pricing_rule_id']);
                if ($ruleRow !== null && !empty($ruleRow['delivery_type_id'])) {
                    $deliveryTypeId = (int) $ruleRow['delivery_type_id'];
                }
            }
            if ($deliveryTypeId !== null) {
                $insertData['delivery_type_id'] = $deliveryTypeId;
            }

            // Nombre usado solo para el matching de pricing (compatibilidad).
            $deliveryType = $explicitDeliveryType !== '' ? $explicitDeliveryType : 'aereo';

            $insertData['delivery_option'] = $data['delivery_option'] ?? 'branch';
            if (isset($data['local_delivery_type'])) {
                $localDtId = $model->getDeliveryTypeId($data['local_delivery_type']);
                if ($localDtId !== null) {
                    $insertData['local_delivery_type_id'] = $localDtId;
                }
            }

            if (isset($data['shipping_service'])) {
                $ssId = $model->getShippingServiceId($data['shipping_service']);
                if ($ssId !== null) {
                    $insertData['shipping_service_id'] = $ssId;
                }
            }

            if (isset($data['delivery_option'])) {
                $doptId = $model->getDeliveryOptionId($data['delivery_option']);
                if ($doptId !== null) {
                    $insertData['delivery_option_id'] = $doptId;
                }
            }

            $statusId = $model->getStatusId('pending');
            if ($statusId !== null) {
                $insertData['status_id'] = $statusId;
            }

            // Origin: compra online (Miami) vs directo en Nicaragua (local).
            $origin = ($data['origin'] ?? '') === 'local' ? 'local' : 'online';
            $insertData['origin'] = $origin;

            $statusName = 'received';
            $statusDescription = 'Paquete recibido en almacén Miami';
            if ($origin === 'local') {
                $statusName = 'in_nicaragua';
                $statusDescription = 'Paquete recibido en Nicaragua, listo para entrega local';
            }

            $initialStatusId = $model->getStatusId($statusName);
            if ($initialStatusId !== null) {
                $insertData['status_id'] = $initialStatusId;
                if ($statusName === 'received') {
                    $insertData['received_at'] = date('Y-m-d H:i:s');
                }
            }

            $pricing = [
                'volumetric_weight' => 0,
                'chargeable_weight' => 0,
                'flete_cost' => 0,
                'delivery_cost' => 0,
                'total_amount' => 0,
            ];

            if ($pricingOverride !== null) {
                $pricing = [
                    'volumetric_weight' => (float) ($pricingOverride['volumetric_weight'] ?? 0),
                    'chargeable_weight' => (float) ($pricingOverride['chargeable_weight'] ?? 0),
                    'flete_cost' => (float) ($pricingOverride['flete_cost'] ?? 0),
                    'delivery_cost' => (float) ($pricingOverride['delivery_cost'] ?? 0),
                    'total_amount' => (float) ($pricingOverride['total_amount'] ?? 0),
                ];
                $insertData['volumetric_weight'] = $pricing['volumetric_weight'];
                $insertData['chargeable_weight'] = $pricing['chargeable_weight'];
                $insertData['flete_cost'] = $pricing['flete_cost'];
                $insertData['delivery_cost'] = $pricing['delivery_cost'];
                $insertData['shipping_cost'] = $pricing['total_amount'];
                if (isset($pricingOverride['pricing_rule_id'])) {
                    $insertData['pricing_rule_id'] = (int) $pricingOverride['pricing_rule_id'];
                }
            } else {
                $pricingService = new PricingService();
                $zoneId = isset($data['zone_id']) ? (int) $data['zone_id'] : null;
                $zone = null;
                if ($zoneId !== null) {
                    $zoneStmt = $this->db->prepare('SELECT * FROM zone WHERE id = :id LIMIT 1');
                    $zoneStmt->execute(['id' => $zoneId]);
                    $zoneRow = $zoneStmt->fetch();
                    $zone = $zoneRow ?: null;
                }

                $ruleModel = new PricingRuleModel($this->db);
                $rule = null;
                if (isset($data['pricing_rule_id'])) {
                    $rule = $ruleModel->findById((int) $data['pricing_rule_id']);
                }
                if ($rule === null) {
                    $rule = $ruleModel->findBestMatch($zoneId, $deliveryType, null);
                }

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
            }

            if (isset($data['storage_category_id']) && $data['storage_category_id'] !== '' && $data['storage_category_id'] !== null) {
                $insertData['storage_category_id'] = (int) $data['storage_category_id'];
            }

            $packageId = $model->create($insertData);

            try {
                $storageService = new PackageStorageService($this->db);
                $storageService->assign(
                    $packageId,
                    $origin,
                    $insertData['storage_category_id'] ?? null,
                    isset($data['storage_location_id']) && $data['storage_location_id'] !== '' ? (int) $data['storage_location_id'] : null,
                    (int) $payload['sub'],
                    'stored',
                    'Ubicado al recibir el paquete'
                );
            } catch (\Throwable $e) {
                apiLog('ERROR', 'Error asignando almacen al paquete ' . $packageId . ': ' . $e->getMessage());
            }

            $trackingModel = new TrackingEventModel($this->db);
            $trackingModel->create([
                'package_id' => $packageId,
                'status' => $statusName,
                'description' => $statusDescription,
                'recorded_at' => date('Y-m-d H:i:s'),
                'created_by' => (int) $payload['sub'],
            ]);

            $invoiceService = new InvoiceService($this->db);
            $invoiceService->createForPackage($packageId, $insertData, $client, $payload);

            if ($ownTx) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        $package = $model->findById($packageId);
        $invoice = (new InvoiceModel($this->db))->findByPackageId($packageId);

        if ($notify) {
            try {
                $notifier = new PackageNotifierService($this->db);
                $notifier->notifyStatusChange($package, 'pending', $statusName, $statusDescription);
            } catch (\Throwable $e) {
                apiLog('ERROR', 'Error notificando paquete ' . $packageId . ': ' . $e->getMessage());
            }
        }

        return ['package' => $package, 'pricing' => $pricing, 'invoice' => $invoice];
    }

    public static function generateRandomTrackingCode(string $prefix, int $length = 8): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return "{$prefix}-{$code}";
    }
}
