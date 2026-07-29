<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\ServiceDeliveryOptionModel;
use App\Config\Database;

class ServiceDeliveryOptionController
{
    private ServiceDeliveryOptionModel $model;

    public function __construct()
    {
        $this->model = new ServiceDeliveryOptionModel(Database::getConnection());
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();
        if (!hasPermission($payload, 'shipping_service', 'read')) {
            jsonError('No tenés permiso', 403);
        }
        $serviceId = isset($_GET['shipping_service_id']) ? (int) $_GET['shipping_service_id'] : null;
        if ($serviceId === null) {
            jsonError('shipping_service_id es requerido', 422);
        }
        $items = $this->model->getByService($serviceId);
        jsonSuccess(['service_options' => $items]);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('service_delivery_option_update', 20, 900);
        if (!hasPermission($payload, 'shipping_service', 'edit')) {
            jsonError('No tenés permiso', 403);
        }
        $data = getJsonInput();
        $serviceId = (int) ($data['shipping_service_id'] ?? 0);
        if ($serviceId <= 0) {
            jsonError('shipping_service_id inválido', 422);
        }
        $optionIds = [];
        if (isset($data['delivery_option_ids']) && is_array($data['delivery_option_ids'])) {
            foreach ($data['delivery_option_ids'] as $opt) {
                if (is_array($opt)) {
                    $optionIds[] = [
                        'delivery_option_id' => (int) ($opt['delivery_option_id'] ?? 0),
                        'is_default' => !empty($opt['is_default']),
                    ];
                } else {
                    $optionIds[] = (int) $opt;
                }
            }
        }
        try {
            $this->model->updateForService($serviceId, $optionIds);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                jsonError('Uno o más IDs de opción de entrega no existen', 409);
            }
            throw $e;
        }
        jsonSuccess(['message' => 'Asignación actualizada']);
    }
}
