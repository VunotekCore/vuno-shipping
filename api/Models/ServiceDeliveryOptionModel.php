<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class ServiceDeliveryOptionModel
{
    public function __construct(private PDO $db) {}

    public function getByService(int $serviceId): array
    {
        $stmt = $this->db->prepare("
            SELECT do.id, do.name, do.description, sdo.is_default
            FROM service_delivery_option sdo
            JOIN delivery_option do ON do.id = sdo.delivery_option_id
            WHERE sdo.shipping_service_id = :service_id
            ORDER BY do.name ASC
        ");
        $stmt->execute(['service_id' => $serviceId]);
        return $stmt->fetchAll();
    }

    public function getAssignedIds(int $serviceId): array
    {
        $stmt = $this->db->prepare('SELECT delivery_option_id FROM service_delivery_option WHERE shipping_service_id = :service_id');
        $stmt->execute(['service_id' => $serviceId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function updateForService(int $serviceId, array $optionIds): void
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('DELETE FROM service_delivery_option WHERE shipping_service_id = :service_id');
            $stmt->execute(['service_id' => $serviceId]);

            if (!empty($optionIds)) {
                $insert = $this->db->prepare('INSERT INTO service_delivery_option (shipping_service_id, delivery_option_id, is_default) VALUES (:service_id, :option_id, :is_default)');
                foreach ($optionIds as $opt) {
                    $optId = is_array($opt) ? ($opt['delivery_option_id'] ?? $opt) : $opt;
                    $isDefault = is_array($opt) ? ($opt['is_default'] ?? false) : false;
                    $insert->execute([
                        'service_id' => $serviceId,
                        'option_id' => (int) $optId,
                        'is_default' => $isDefault ? 1 : 0,
                    ]);
                }
            }

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
