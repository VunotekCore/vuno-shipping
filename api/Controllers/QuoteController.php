<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\QuoteModel;
use App\Models\QuoteItemModel;
use App\Models\PackageModel;
use App\Models\PricingRuleModel;
use App\Models\BillingConfigModel;
use App\Services\PricingService;
use App\Services\PackageService;
use App\Config\Database;
use PDO;

class QuoteController
{
    private QuoteModel $model;
    private QuoteItemModel $itemModel;
    private PackageModel $packageModel;
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->model = new QuoteModel($this->db);
        $this->itemModel = new QuoteItemModel($this->db);
        $this->packageModel = new PackageModel($this->db);
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'quote', 'read')) {
            jsonError('No tenés permiso para ver cotizaciones', 403);
        }

        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $filters = [
            'status' => $_GET['status'] ?? null,
            'search' => $_GET['search'] ?? null,
            'from'   => $_GET['from'] ?? null,
            'to'     => $_GET['to'] ?? null,
        ];
        if (isset($_GET['client_id']) && $_GET['client_id'] !== '') {
            $filters['client_id'] = (int) $_GET['client_id'];
        }

        if ($this->isVendor($payload)) {
            $filters['agent_id'] = (int) $payload['sub'];
        } elseif (isset($_GET['agent_id']) && $_GET['agent_id'] !== '') {
            $filters['agent_id'] = (int) $_GET['agent_id'];
        }

        $this->model->expireOverdue();

        $total = $this->model->count($filters);
        $quotes = $this->model->listPaginated($limit, $offset, $filters);

        jsonSuccess([
            'quotes' => $quotes,
            'pagination' => [
                'page'  => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / $limit),
            ],
            'summary' => $this->model->countByStatus($filters['agent_id'] ?? null),
        ]);
    }

    public function get(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'quote', 'read')) {
            jsonError('No tenés permiso para ver cotizaciones', 403);
        }

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            jsonError('ID de cotización inválido', 422);
        }

        $this->model->expireOverdue();

        $quote = $this->model->findById($id);
        if ($quote === null) {
            jsonError('Cotización no encontrada', 404);
        }
        $this->assertOwnership($payload, $quote);

        jsonSuccess([
            'quote' => $quote,
            'items' => $this->itemModel->listByQuote($id),
            'packages' => $this->model->listPackages($id),
        ]);
    }

    public function create(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('quote_create', 10, 900);

        if (!hasPermission($payload, 'quote', 'create')) {
            jsonError('No tenés permiso para crear cotizaciones', 403);
        }

        $data = getJsonInput();
        $itemsIn = $this->extractItems($data);
        $context = $this->buildContext($data);
        $pricedItems = $this->priceItems($itemsIn, $context);
        $totals = $this->summarize($pricedItems);

        $agentId = $this->isVendor($payload)
            ? (int) $payload['sub']
            : (int) ($data['agent_id'] ?? $payload['sub']);

        $this->db->beginTransaction();
        try {
            $quoteNumber = $this->model->nextNumber('quote');

            $quoteId = $this->model->create(array_merge($this->recipientData($data), $context, $totals, [
                'quote_number' => $quoteNumber,
                'agent_id' => $agentId,
                'created_by' => (int) $payload['sub'],
            ]));

            $this->itemModel->insertMany($quoteId, $pricedItems);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        $quote = $this->model->findById($quoteId);
        jsonSuccess([
            'quote' => $quote,
            'items' => $this->itemModel->listByQuote($quoteId),
        ], 'Cotización creada', 201);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('quote_update', 20, 900);

        if (!hasPermission($payload, 'quote', 'edit')) {
            jsonError('No tenés permiso para editar cotizaciones', 403);
        }

        $data = getJsonInput();
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            jsonError('ID de cotización inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Cotización no encontrada', 404);
        }
        $this->assertOwnership($payload, $existing);

        if (in_array($existing['status'], ['converted', 'cancelled'], true)) {
            jsonError('No se puede editar una cotización convertida o cancelada', 409);
        }

        $itemsIn = $this->extractItems($data);
        $context = $this->buildContext($data);
        $pricedItems = $this->priceItems($itemsIn, $context);
        $totals = $this->summarize($pricedItems);

        $updateData = array_merge($this->recipientData($data), $context, $totals);

        // Un vendedor no puede reasignar la cotización a otro agente.
        if (!$this->isVendor($payload) && array_key_exists('agent_id', $data)) {
            $updateData['agent_id'] = (int) $data['agent_id'];
        }

        $this->db->beginTransaction();
        try {
            $this->model->update($id, $updateData);
            $this->itemModel->deleteByQuote($id);
            $this->itemModel->insertMany($id, $pricedItems);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        $quote = $this->model->findById($id);
        jsonSuccess([
            'quote' => $quote,
            'items' => $this->itemModel->listByQuote($id),
        ], 'Cotización actualizada');
    }

    public function status(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('quote_status', 20, 900);

        if (!hasPermission($payload, 'quote', 'edit')) {
            jsonError('No tenés permiso para cambiar el estado', 403);
        }

        $data = getJsonInput();
        $id = (int) ($data['id'] ?? 0);
        $status = (string) ($data['status'] ?? '');

        $allowed = ['draft', 'sent', 'accepted', 'rejected', 'expired', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            jsonError('Estado inválido. Usá el endpoint de conversión para convertirla.', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Cotización no encontrada', 404);
        }
        $this->assertOwnership($payload, $existing);

        if ($existing['status'] === 'converted') {
            jsonError('Una cotización convertida no puede cambiar de estado', 409);
        }

        $this->model->update($id, ['status' => $status]);
        $quote = $this->model->findById($id);

        jsonSuccess(['quote' => $quote], 'Estado actualizado');
    }

    public function delete(): never
    {
        requireMethod('DELETE');
        $payload = requireAuthMiddleware(true);
        rateLimit('quote_delete', 5, 900);

        if (!hasPermission($payload, 'quote', 'delete')) {
            jsonError('No tenés permiso para eliminar cotizaciones', 403);
        }

        $data = getJsonInput();
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            jsonError('ID de cotización inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Cotización no encontrada', 404);
        }
        $this->assertOwnership($payload, $existing);

        if ($existing['status'] === 'converted') {
            jsonError('No se puede eliminar una cotización convertida', 409);
        }

        $this->model->delete($id);
        jsonSuccess(null, 'Cotización eliminada');
    }

    public function convert(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('quote_convert', 10, 900);

        if (!hasPermission($payload, 'quote', 'edit')) {
            jsonError('No tenés permiso para convertir cotizaciones', 403);
        }

        $data = getJsonInput();
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            jsonError('ID de cotización inválido', 422);
        }

        $quote = $this->model->findById($id);
        if ($quote === null) {
            jsonError('Cotización no encontrada', 404);
        }
        $this->assertOwnership($payload, $quote);

        if ($quote['status'] === 'converted') {
            jsonError('La cotización ya fue convertida', 409);
        }
        if (!in_array($quote['status'], ['draft', 'sent', 'accepted'], true)) {
            jsonError('Solo se pueden convertir cotizaciones en borrador, enviadas o aceptadas', 409);
        }

        $items = $this->itemModel->listByQuote($id);
        if (empty($items)) {
            jsonError('La cotización no tiene ítems para convertir', 422);
        }
        if (empty($quote['recipient_phone']) || empty($quote['recipient_address'])) {
            jsonError('El destinatario necesita teléfono y dirección para generar los paquetes', 422);
        }

        $deliveryTypeName = $this->nameForId('delivery_type', (int) ($quote['delivery_type_id'] ?? 0));
        $shippingServiceName = $this->nameForId('shipping_service', (int) ($quote['shipping_service_id'] ?? 0));

        $this->db->beginTransaction();
        try {
            $service = new PackageService($this->db);
            $created = [];

            foreach ($items as $item) {
                $pkgData = [
                    'sender_name' => $quote['recipient_name'],
                    'sender_phone' => $quote['recipient_phone'],
                    'recipient_name' => $quote['recipient_name'],
                    'recipient_phone' => $quote['recipient_phone'],
                    'recipient_address' => $quote['recipient_address'],
                    'recipient_city' => $quote['recipient_city'],
                    'recipient_department' => $quote['recipient_department'],
                    'latitude' => $quote['latitude'],
                    'longitude' => $quote['longitude'],
                    'client_id' => $quote['client_id'],
                    'quote_id' => $quote['id'],
                    'origin' => $quote['origin'],
                    'zone_id' => $quote['zone_id'],
                    'delivery_type' => $deliveryTypeName ?? 'aereo',
                    'delivery_option' => $quote['delivery_option'] ?? 'branch',
                    'shipping_service' => $shippingServiceName,
                    'description' => $item['description'],
                    'weight' => $item['weight'],
                    'length' => $item['length'],
                    'width' => $item['width'],
                    'height' => $item['height'],
                    'declared_value' => $item['declared_value'],
                    'pickup_required' => $quote['pickup_required'],
                    'pickup_vehicle_id' => $quote['pickup_vehicle_id'],
                    'notes' => 'Cotización ' . $quote['quote_number'],
                ];

                $override = [
                    'volumetric_weight' => $item['volumetric_weight'],
                    'chargeable_weight' => $item['chargeable_weight'],
                    'flete_cost' => $item['flete_cost'],
                    'delivery_cost' => $item['delivery_cost'],
                    'total_amount' => $item['total_amount'],
                ];

                $result = $service->create($pkgData, $payload, $override, [
                    'useTransaction' => false,
                    'notify' => false,
                ]);
                $created[] = $result['package'];
            }

            [$cType, $cValue, $cAmount] = $this->commissionFor($quote);
            $this->model->markConverted($id, $cType, $cValue, $cAmount);

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        $updated = $this->model->findById($id);
        jsonSuccess([
            'quote' => $updated,
            'packages' => $this->model->listPackages($id),
        ], 'Cotización convertida en paquete(s)');
    }

    public function stats(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'quote', 'read')) {
            jsonError('No tenés permiso para ver cotizaciones', 403);
        }

        $from = (string) ($_GET['from'] ?? date('Y-m-01'));
        $to = (string) ($_GET['to'] ?? date('Y-m-d'));

        $agentId = $this->isVendor($payload)
            ? (int) $payload['sub']
            : (isset($_GET['agent_id']) && $_GET['agent_id'] !== '' ? (int) $_GET['agent_id'] : null);

        $this->model->expireOverdue();

        jsonSuccess([
            'summary' => $this->model->countByStatus($agentId, $from, $to),
            'commissions' => $this->model->commissionSummary($agentId, $from, $to),
        ]);
    }

    // --- helpers ---

    private function isVendor(array $payload): bool
    {
        return ($payload['role_slug'] ?? '') === 'vendedor';
    }

    private function assertOwnership(array $payload, array $quote): void
    {
        if ($this->isVendor($payload) && (int) ($quote['agent_id'] ?? 0) !== (int) $payload['sub']) {
            jsonError('No tenés permiso sobre esta cotización', 403);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractItems(array $data): array
    {
        $items = $data['items'] ?? [];
        if (!is_array($items) || count($items) === 0) {
            jsonError('La cotización debe tener al menos un ítem', 422);
        }
        return array_values($items);
    }

    private function recipientData(array $data): array
    {
        $recipientName = trim((string) ($data['recipient_name'] ?? ''));
        if ($recipientName === '') {
            jsonError('El nombre del destinatario es requerido', 422);
        }

        $out = ['recipient_name' => sanitizeString($recipientName, 255)];

        if (array_key_exists('recipient_company', $data)) {
            $out['recipient_company'] = ($data['recipient_company'] !== null && $data['recipient_company'] !== '')
                ? sanitizeString((string) $data['recipient_company'], 150) : null;
        }
        if (isset($data['client_id']) && $data['client_id'] !== '') {
            $out['client_id'] = (int) $data['client_id'];
        }
        if (array_key_exists('recipient_phone', $data)) {
            $out['recipient_phone'] = $data['recipient_phone'] !== null
                ? sanitizeString((string) $data['recipient_phone'], 50) : null;
        }
        if (array_key_exists('recipient_email', $data)) {
            $out['recipient_email'] = !empty($data['recipient_email'])
                ? validateEmail((string) $data['recipient_email']) : null;
        }
        if (array_key_exists('recipient_address', $data)) {
            $out['recipient_address'] = $data['recipient_address'] !== null
                ? sanitizeString((string) $data['recipient_address'], 500) : null;
        }
        if (array_key_exists('recipient_city', $data)) {
            $out['recipient_city'] = $data['recipient_city'] !== null
                ? sanitizeString((string) $data['recipient_city'], 100) : null;
        }
        if (array_key_exists('recipient_department', $data)) {
            $out['recipient_department'] = $data['recipient_department'] !== null
                ? sanitizeString((string) $data['recipient_department'], 100) : null;
        }
        if (array_key_exists('recipient_ruc', $data)) {
            $out['recipient_ruc'] = !empty($data['recipient_ruc'])
                ? validateRuc((string) $data['recipient_ruc']) : null;
        }
        if (isset($data['latitude']) && $data['latitude'] !== '' && $data['latitude'] !== null) {
            $lat = (float) $data['latitude'];
            if ($lat < -90 || $lat > 90) {
                jsonError('Latitud inválida (rango -90 a 90)', 422);
            }
            $out['latitude'] = $lat;
        }
        if (isset($data['longitude']) && $data['longitude'] !== '' && $data['longitude'] !== null) {
            $lng = (float) $data['longitude'];
            if ($lng < -180 || $lng > 180) {
                jsonError('Longitud inválida (rango -180 a 180)', 422);
            }
            $out['longitude'] = $lng;
        }

        return $out;
    }

    private function buildContext(array $data): array
    {
        $deliveryType = (string) ($data['delivery_type'] ?? 'aereo');
        $deliveryOption = (string) ($data['delivery_option'] ?? 'branch');

        $context = [
            'origin' => ($data['origin'] ?? '') === 'local' ? 'local' : 'online',
            'delivery_option' => $deliveryOption,
            'pickup_required' => !empty($data['pickup_required']) ? 1 : 0,
            'pickup_vehicle_id' => null,
        ];

        if (isset($data['pickup_vehicle_id']) && $data['pickup_vehicle_id'] !== '' && $data['pickup_vehicle_id'] !== null) {
            $vehicleId = (int) $data['pickup_vehicle_id'];
            $vehStmt = $this->db->prepare('SELECT id FROM vehicle WHERE id = :id LIMIT 1');
            $vehStmt->execute(['id' => $vehicleId]);
            if ($vehStmt->fetch() === false) {
                jsonError('El vehículo de recolección indicado no existe', 422);
            }
            $context['pickup_vehicle_id'] = $vehicleId;
        }

        if (isset($data['zone_id']) && $data['zone_id'] !== '') {
            $context['zone_id'] = (int) $data['zone_id'];
        }
        $dtId = $this->packageModel->getDeliveryTypeId($deliveryType);
        if ($dtId !== null) {
            $context['delivery_type_id'] = $dtId;
        }
        if (isset($data['shipping_service']) && $data['shipping_service'] !== '') {
            $ssId = $this->packageModel->getShippingServiceId((string) $data['shipping_service']);
            if ($ssId !== null) {
                $context['shipping_service_id'] = $ssId;
            }
        }
        $doptId = $this->packageModel->getDeliveryOptionId($deliveryOption);
        if ($doptId !== null) {
            $context['delivery_option_id'] = $doptId;
        }
        if (isset($data['valid_until']) && $data['valid_until'] !== '') {
            $context['valid_until'] = (string) $data['valid_until'];
        }
        if (isset($data['notes'])) {
            $context['notes'] = sanitizeString((string) $data['notes'], 1000);
        }

        return $context;
    }

    /**
     * Calcula flete/delivery por ítem usando el motor de precios.
     *
     * @param array<int, array<string, mixed>> $itemsIn
     * @return array<int, array<string, mixed>>
     */
    private function priceItems(array $itemsIn, array $context): array
    {
        $zoneId = isset($context['zone_id']) ? (int) $context['zone_id'] : null;
        $deliveryType = (string) ($context['delivery_option'] ?? 'branch');

        $pricingService = new PricingService();
        $ruleModel = new PricingRuleModel($this->db);

        $rule = null;
        if (isset($context['delivery_type_id'])) {
            $dtStmt = $this->db->prepare('SELECT name FROM delivery_type WHERE id = :id LIMIT 1');
            $dtStmt->execute(['id' => (int) $context['delivery_type_id']]);
            $dtRow = $dtStmt->fetch();
            $deliveryTypeName = $dtRow ? $dtRow['name'] : 'aereo';
            $rule = $ruleModel->findBestMatch($zoneId, $deliveryTypeName, null);
        }
        if ($rule === null) {
            $rule = $ruleModel->findBestMatch($zoneId, $deliveryType, null);
        }
        if ($rule === null) {
            jsonError('No se encontró una regla de precios para los parámetros dados', 422);
        }

        $zone = null;
        if ($zoneId !== null) {
            $zoneStmt = $this->db->prepare('SELECT * FROM zone WHERE id = :id LIMIT 1');
            $zoneStmt->execute(['id' => $zoneId]);
            $zoneRow = $zoneStmt->fetch();
            $zone = $zoneRow ?: null;
        }

        $priced = [];
        foreach ($itemsIn as $raw) {
            $item = [
                'description' => isset($raw['description']) ? sanitizeString((string) $raw['description'], 500) : null,
                'quantity' => max(1, (int) ($raw['quantity'] ?? 1)),
                'weight' => isset($raw['weight']) && $raw['weight'] !== '' ? (float) $raw['weight'] : null,
                'length' => isset($raw['length']) && $raw['length'] !== '' ? (float) $raw['length'] : null,
                'width' => isset($raw['width']) && $raw['width'] !== '' ? (float) $raw['width'] : null,
                'height' => isset($raw['height']) && $raw['height'] !== '' ? (float) $raw['height'] : null,
                'declared_value' => isset($raw['declared_value']) && $raw['declared_value'] !== '' ? (float) $raw['declared_value'] : null,
            ];

            $pricing = $pricingService->calculatePackageTotal([
                'weight' => $item['weight'] ?? 0,
                'length' => $item['length'] ?? 0,
                'width' => $item['width'] ?? 0,
                'height' => $item['height'] ?? 0,
                'declared_value' => $item['declared_value'] ?? 0,
                'delivery_option' => $context['delivery_option'] ?? 'branch',
            ], $rule, $zone);

            $item['volumetric_weight'] = $pricing['volumetric_weight'];
            $item['chargeable_weight'] = $pricing['chargeable_weight'];
            $item['flete_cost'] = $pricing['flete_cost'];
            $item['delivery_cost'] = $pricing['delivery_cost'];
            $item['total_amount'] = $pricing['total_amount'];

            $priced[] = $item;
        }

        return $priced;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{subtotal: float, delivery_total: float, base_amount: float, tax_rate: float, tax_amount: float, total_amount: float, currency: string}
     */
    private function summarize(array $items): array
    {
        $subtotal = 0.0;
        $deliveryTotal = 0.0;
        foreach ($items as $item) {
            $subtotal += (float) $item['flete_cost'];
            $deliveryTotal += (float) $item['delivery_cost'];
        }
        $base = round($subtotal + $deliveryTotal, 2);

        $config = (new BillingConfigModel($this->db))->get();
        $taxEnabled = (int) ($config['tax_enabled'] ?? 0) === 1;
        $taxRate = (float) ($config['tax_rate'] ?? 0);
        $taxAmount = $taxEnabled ? round($base * ($taxRate / 100), 2) : 0.00;

        return [
            'subtotal' => round($subtotal, 2),
            'delivery_total' => round($deliveryTotal, 2),
            'base_amount' => $base,
            'tax_rate' => $taxEnabled ? $taxRate : 0.00,
            'tax_amount' => $taxAmount,
            'total_amount' => round($base + $taxAmount, 2),
            'currency' => 'USD',
        ];
    }

    /**
     * @return array{0: ?string, 1: ?float, 2: ?float}
     */
    private function commissionFor(array $quote): array
    {
        $agentId = (int) ($quote['agent_id'] ?? 0);
        if ($agentId <= 0) {
            return [null, null, null];
        }
        $comm = $this->model->getAgentCommission($agentId);
        if ($comm === null || empty($comm['commission_type']) || $comm['commission_value'] === null) {
            return [null, null, null];
        }

        $type = (string) $comm['commission_type'];
        $value = (float) $comm['commission_value'];
        $amount = $type === 'percent'
            ? round((float) $quote['total_amount'] * $value / 100, 2)
            : round($value, 2);

        return [$type, $value, $amount];
    }

    private function nameForId(string $table, int $id): ?string
    {
        if ($id <= 0) {
            return null;
        }
        $allowed = ['delivery_type', 'shipping_service', 'delivery_option'];
        if (!in_array($table, $allowed, true)) {
            return null;
        }
        $stmt = $this->db->prepare("SELECT name FROM {$table} WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? (string) $row['name'] : null;
    }
}
