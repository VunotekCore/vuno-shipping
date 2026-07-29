<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\PricingRuleModel;
use App\Services\PricingService;
use App\Config\Database;

class PricingRuleController
{
    private PricingRuleModel $model;

    public function __construct()
    {
        $this->model = new PricingRuleModel(Database::getConnection());
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'pricing_rule', 'read')) {
            jsonError('No tenés permiso para ver reglas de pricing', 403);
        }

        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->model->count();
        $rules = $this->model->listPaginated($limit, $offset);

        jsonSuccess([
            'pricing_rules' => $rules,
            'pagination' => [
                'page'  => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    public function listAll(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'pricing_rule', 'read')) {
            jsonError('No tenés permiso para ver reglas de pricing', 403);
        }

        $rules = $this->model->listAll();
        jsonSuccess(['pricing_rules' => $rules]);
    }

    public function create(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('pricing_rule_create', 10, 900);

        if (!hasPermission($payload, 'pricing_rule', 'create')) {
            jsonError('No tenés permiso para crear reglas de pricing', 403);
        }

        $data = getJsonInput();

        $name = $data['name'] ?? '';
        if ($name === '') {
            jsonError('El nombre es requerido', 422);
        }

        $insertData = ['name' => sanitizeString($name, 255)];

        if (isset($data['type_id'])) {
            $typeId = (int) $data['type_id'];
            if (!$this->model->validateTypeExists($typeId)) {
                jsonError('El tipo de pricing especificado no existe', 422);
            }
            $insertData['type_id'] = $typeId;
        }
        if (isset($data['delivery_type_id'])) {
            $dtId = (int) $data['delivery_type_id'];
            if (!$this->model->validateDeliveryTypeExists($dtId)) {
                jsonError('El tipo de entrega especificado no existe', 422);
            }
            $insertData['delivery_type_id'] = $dtId;
        }
        if (isset($data['base_price'])) {
            $insertData['base_price'] = (float) $data['base_price'];
        }
        if (isset($data['min_weight'])) {
            $insertData['min_weight'] = (float) $data['min_weight'];
        }
        if (isset($data['max_weight'])) {
            $insertData['max_weight'] = (float) $data['max_weight'];
        }
        if (isset($data['price_per_kg'])) {
            $insertData['price_per_kg'] = (float) $data['price_per_kg'];
        }
        if (isset($data['price_per_volume'])) {
            $insertData['price_per_volume'] = (float) $data['price_per_volume'];
        }
        if (isset($data['price_per_km'])) {
            $insertData['price_per_km'] = (float) $data['price_per_km'];
        }
        if (isset($data['price_per_zone'])) {
            $insertData['price_per_zone'] = (float) $data['price_per_zone'];
        }
        if (isset($data['percentage_of_value'])) {
            $insertData['percentage_of_value'] = (float) $data['percentage_of_value'];
        }
        if (isset($data['min_price'])) {
            $insertData['min_price'] = (float) $data['min_price'];
        }
        if (isset($data['volumetric_divisor'])) {
            $vd = (int) $data['volumetric_divisor'];
            if ($vd <= 0) jsonError('El divisor volumétrico debe ser mayor a 0', 422);
            $insertData['volumetric_divisor'] = $vd;
        }
        if (isset($data['is_active'])) {
            $insertData['is_active'] = (int) $data['is_active'];
        }

        $db = \App\Config\Database::getConnection();
        $db->beginTransaction();

        try {
            $id = $this->model->create($insertData);

            if (isset($data['zone_ids']) && is_array($data['zone_ids'])) {
                $this->model->setZones($id, $data['zone_ids']);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            apiLog('ERROR', 'Error creating pricing rule: ' . $e->getMessage());
            jsonError('Error al crear la regla de pricing', 500);
        }

        $rule = $this->model->findById($id);
        $rule['zones'] = $this->model->getZones($id);

        jsonSuccess(['pricing_rule' => $rule], 'Regla de pricing creada', 201);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('pricing_rule_update', 20, 900);

        if (!hasPermission($payload, 'pricing_rule', 'edit')) {
            jsonError('No tenés permiso para editar reglas de pricing', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            jsonError('ID de regla inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Regla de pricing no encontrada', 404);
        }

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = sanitizeString($data['name'], 255);
        }
        if (array_key_exists('type_id', $data)) {
            if ($data['type_id'] !== null) {
                $typeId = (int) $data['type_id'];
                if (!$this->model->validateTypeExists($typeId)) {
                    jsonError('El tipo de pricing especificado no existe', 422);
                }
                $updateData['type_id'] = $typeId;
            } else {
                $updateData['type_id'] = null;
            }
        }
        if (array_key_exists('delivery_type_id', $data)) {
            if ($data['delivery_type_id'] !== null) {
                $dtId = (int) $data['delivery_type_id'];
                if (!$this->model->validateDeliveryTypeExists($dtId)) {
                    jsonError('El tipo de entrega especificado no existe', 422);
                }
                $updateData['delivery_type_id'] = $dtId;
            } else {
                $updateData['delivery_type_id'] = null;
            }
        }
        if (array_key_exists('base_price', $data)) {
            $updateData['base_price'] = (float) $data['base_price'];
        }
        if (array_key_exists('min_weight', $data)) {
            $updateData['min_weight'] = $data['min_weight'] !== null ? (float) $data['min_weight'] : null;
        }
        if (array_key_exists('max_weight', $data)) {
            $updateData['max_weight'] = $data['max_weight'] !== null ? (float) $data['max_weight'] : null;
        }
        if (array_key_exists('price_per_kg', $data)) {
            $updateData['price_per_kg'] = $data['price_per_kg'] !== null ? (float) $data['price_per_kg'] : null;
        }
        if (array_key_exists('price_per_volume', $data)) {
            $updateData['price_per_volume'] = $data['price_per_volume'] !== null ? (float) $data['price_per_volume'] : null;
        }
        if (array_key_exists('price_per_km', $data)) {
            $updateData['price_per_km'] = $data['price_per_km'] !== null ? (float) $data['price_per_km'] : null;
        }
        if (array_key_exists('price_per_zone', $data)) {
            $updateData['price_per_zone'] = $data['price_per_zone'] !== null ? (float) $data['price_per_zone'] : null;
        }
        if (array_key_exists('percentage_of_value', $data)) {
            $updateData['percentage_of_value'] = (float) $data['percentage_of_value'];
        }
        if (array_key_exists('min_price', $data)) {
            $updateData['min_price'] = $data['min_price'] !== null ? (float) $data['min_price'] : null;
        }
        if (array_key_exists('volumetric_divisor', $data)) {
            $vd = (int) $data['volumetric_divisor'];
            if ($vd <= 0) jsonError('El divisor volumétrico debe ser mayor a 0', 422);
            $updateData['volumetric_divisor'] = $vd;
        }
        if (array_key_exists('is_active', $data)) {
            $updateData['is_active'] = (int) $data['is_active'];
        }

        if (empty($updateData)) {
            jsonError('No hay datos para actualizar', 422);
        }

        $db = \App\Config\Database::getConnection();
        $db->beginTransaction();

        try {
            $this->model->update($id, $updateData);

            if (isset($data['zone_ids']) && is_array($data['zone_ids'])) {
                $this->model->setZones($id, $data['zone_ids']);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            apiLog('ERROR', 'Error updating pricing rule: ' . $e->getMessage());
            jsonError('Error al actualizar la regla de pricing', 500);
        }

        $updated = $this->model->findById($id);
        $updated['zones'] = $this->model->getZones($id);

        jsonSuccess(['pricing_rule' => $updated], 'Regla de pricing actualizada');
    }

    public function delete(): never
    {
        requireMethod('DELETE');
        $payload = requireAuthMiddleware(true);
        rateLimit('pricing_rule_delete', 5, 900);

        if (!hasPermission($payload, 'pricing_rule', 'delete')) {
            jsonError('No tenés permiso para eliminar reglas de pricing', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            jsonError('ID de regla inválido', 422);
        }

        $existing = $this->model->findById($id);
        if ($existing === null) {
            jsonError('Regla de pricing no encontrada', 404);
        }

        $this->model->delete($id);
        jsonSuccess(null, 'Regla de pricing eliminada');
    }

    public function calculate(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);

        if (!hasPermission($payload, 'pricing_rule', 'read')) {
            jsonError('No tenés permiso para calcular pricing', 403);
        }

        $data = getJsonInput();

        $packages = $data['packages'] ?? [];
        $zoneId = isset($data['zone_id']) ? (int) $data['zone_id'] : null;
        $deliveryType = $data['delivery_type'] ?? 'door';
        $declaredValue = isset($data['declared_value']) ? (float) $data['declared_value'] : null;
        $type = $data['type'] ?? null;

        if (empty($packages)) {
            jsonError('Se requiere al menos un paquete', 422);
        }

        $service = new PricingService();
        $result = $service->calculate($packages, $zoneId, $deliveryType, $declaredValue, $type);

        jsonSuccess($result);
    }

    public function listDeliveryTypes(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'pricing_rule', 'read')) {
            jsonError('No tenés permiso', 403);
        }

        $db = Database::getConnection();
        $stmt = $db->query('SELECT id, name, description, category FROM delivery_type ORDER BY name ASC');
        jsonSuccess(['delivery_types' => $stmt->fetchAll()]);
    }

    public function listPricingRuleTypes(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'pricing_rule', 'read')) {
            jsonError('No tenés permiso', 403);
        }

        $db = Database::getConnection();
        $stmt = $db->query('SELECT id, name, description FROM pricing_rule_type ORDER BY name ASC');
        jsonSuccess(['pricing_rule_types' => $stmt->fetchAll()]);
    }

    public function resolveFromRule(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'pricing_rule', 'read')) {
            jsonError('No tenés permiso', 403);
        }

        $ruleId = isset($_GET['pricing_rule_id']) ? (int) $_GET['pricing_rule_id'] : null;
        if (!$ruleId) {
            jsonError('pricing_rule_id es requerido', 422);
        }

        $rule = $this->model->findById($ruleId);
        if (!$rule) {
            jsonError('Regla de precio no encontrada', 404);
        }

        $shippingService = null;
        $deliveryOptions = [];

        if ($rule['delivery_type_id']) {
            $db = Database::getConnection();
            $ssStmt = $db->prepare('SELECT id, name, description FROM shipping_service WHERE delivery_type_id = :dt_id AND is_active = 1 LIMIT 1');
            $ssStmt->execute(['dt_id' => $rule['delivery_type_id']]);
            $shippingService = $ssStmt->fetch();

            if ($shippingService) {
                $optStmt = $db->prepare("
                    SELECT do.id, do.name, do.description, sdo.is_default
                    FROM service_delivery_option sdo
                    JOIN delivery_option do ON do.id = sdo.delivery_option_id
                    WHERE sdo.shipping_service_id = :ss_id AND do.is_active = 1
                    ORDER BY do.name ASC
                ");
                $optStmt->execute(['ss_id' => $shippingService['id']]);
                $deliveryOptions = $optStmt->fetchAll();
            }
        }

        jsonSuccess([
            'shipping_service' => $shippingService,
            'delivery_options' => $deliveryOptions,
        ]);
    }
}
