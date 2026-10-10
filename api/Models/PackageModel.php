<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class PackageModel
{
    public function __construct(private PDO $db) {}

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM package')->fetchColumn();
    }

    public function countByStatus(string $status): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM package p
            JOIN package_status ps ON ps.id = p.status_id
            WHERE ps.name = :status
        ");
        $stmt->execute(['status' => $status]);
        return (int) $stmt->fetchColumn();
    }

    public function countActive(): int
    {
        return (int) $this->db->query("
            SELECT COUNT(*) FROM package p
            JOIN package_status ps ON ps.id = p.status_id
            WHERE ps.name NOT IN ('delivered', 'returned', 'cancelled')
        ")->fetchColumn();
    }

    public function countDeliveredToday(): int
    {
        return (int) $this->db->query("
            SELECT COUNT(*) FROM package
            WHERE delivered_at IS NOT NULL AND DATE(delivered_at) = CURDATE()
        ")->fetchColumn();
    }

    public function listPaginated(int $limit, int $offset, ?string $status = null, ?int $clientId = null, ?int $driverId = null): array
    {
        $where = [];
        $params = [];

        if ($status !== null) {
            $where[] = 'ps.name = :status';
            $params['status'] = $status;
        }
        if ($clientId !== null) {
            $where[] = 'p.client_id = :client_id';
            $params['client_id'] = $clientId;
        }
        if ($driverId !== null) {
            $where[] = 'p.assigned_to = :driver_id';
            $params['driver_id'] = $driverId;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->db->prepare("
            SELECT COUNT(*) FROM package p
            LEFT JOIN package_status ps ON ps.id = p.status_id
            {$whereClause}
        ");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare("
            SELECT p.id, p.tracking_number, p.client_id, p.quote_id, c.name AS client_name,
                   q.quote_number,
                   p.sender_name, p.sender_phone, p.recipient_name, p.recipient_phone,
                   p.recipient_address, p.recipient_city, p.recipient_department,
                   p.latitude, p.longitude,
                   p.zone_id, z.name AS zone_name,
                   p.description, p.weight, p.length, p.width, p.height,
                   p.volumetric_weight, p.chargeable_weight,
                   p.declared_value, p.flete_cost, p.delivery_cost, p.shipping_cost,
                   p.status_id, ps.name AS status_name,
                   p.delivery_type_id, dt.name AS delivery_type_name,
                   p.delivery_option,
                   p.local_delivery_type_id, ldt.name AS local_delivery_type_name,
                   p.shipping_service_id, ss.name AS shipping_service_name,
                   p.delivery_option_id, dopt.name AS delivery_option_name,
                   p.assigned_to, u.name AS assigned_to_name,
                   p.origin, p.pickup_required, p.pickup_vehicle_id, v.plate AS pickup_vehicle_plate,
                   p.received_at, p.delivered_at, p.created_at,
                   inv.id AS invoice_id, inv.invoice_number, inv.status AS invoice_status
            FROM package p
            LEFT JOIN client c ON c.id = p.client_id
            LEFT JOIN quote q ON q.id = p.quote_id
            LEFT JOIN vehicle v ON v.id = p.pickup_vehicle_id
            LEFT JOIN zone z ON z.id = p.zone_id
            LEFT JOIN package_status ps ON ps.id = p.status_id
            LEFT JOIN delivery_type dt ON dt.id = p.delivery_type_id
            LEFT JOIN delivery_type ldt ON ldt.id = p.local_delivery_type_id
            LEFT JOIN shipping_service ss ON ss.id = p.shipping_service_id
            LEFT JOIN delivery_option dopt ON dopt.id = p.delivery_option_id
            LEFT JOIN `user` u ON u.id = p.assigned_to
            LEFT JOIN invoice inv ON inv.id = (
                SELECT i2.id FROM invoice i2 WHERE i2.package_id = p.id ORDER BY i2.id DESC LIMIT 1
            )
            {$whereClause}
            ORDER BY p.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();

        return ['packages' => $stmt->fetchAll(), 'total' => $total];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, c.name AS client_name, c.email AS client_email, c.phone AS client_phone, ps.name AS status_name,
                   dt.name AS delivery_type_name, ldt.name AS local_delivery_type_name,
                   ss.name AS shipping_service_name, dopt.name AS delivery_option_name,
                   u.name AS assigned_to_name,
                   pr.name AS pricing_rule_name, z.name AS zone_name,
                   v.plate AS pickup_vehicle_plate,
                   inv.id AS invoice_id, inv.invoice_number, inv.status AS invoice_status
            FROM package p
            LEFT JOIN client c ON c.id = p.client_id
            LEFT JOIN vehicle v ON v.id = p.pickup_vehicle_id
            LEFT JOIN package_status ps ON ps.id = p.status_id
            LEFT JOIN delivery_type dt ON dt.id = p.delivery_type_id
            LEFT JOIN delivery_type ldt ON ldt.id = p.local_delivery_type_id
            LEFT JOIN shipping_service ss ON ss.id = p.shipping_service_id
            LEFT JOIN delivery_option dopt ON dopt.id = p.delivery_option_id
            LEFT JOIN zone z ON z.id = p.zone_id
            LEFT JOIN `user` u ON u.id = p.assigned_to
            LEFT JOIN pricing_rule pr ON pr.id = p.pricing_rule_id
            LEFT JOIN invoice inv ON inv.id = (
                SELECT i2.id FROM invoice i2 WHERE i2.package_id = p.id ORDER BY i2.id DESC LIMIT 1
            )
            WHERE p.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByTrackingNumber(string $trackingNumber): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, c.name AS client_name, ps.name AS status_name,
                   dt.name AS delivery_type_name, ldt.name AS local_delivery_type_name,
                   ss.name AS shipping_service_name, dopt.name AS delivery_option_name,
                   z.name AS zone_name
            FROM package p
            LEFT JOIN client c ON c.id = p.client_id
            LEFT JOIN package_status ps ON ps.id = p.status_id
            LEFT JOIN delivery_type dt ON dt.id = p.delivery_type_id
            LEFT JOIN delivery_type ldt ON ldt.id = p.local_delivery_type_id
            LEFT JOIN shipping_service ss ON ss.id = p.shipping_service_id
            LEFT JOIN delivery_option dopt ON dopt.id = p.delivery_option_id
            LEFT JOIN zone z ON z.id = p.zone_id
            WHERE p.tracking_number = :tracking_number LIMIT 1
        ");
        $stmt->execute(['tracking_number' => $trackingNumber]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO package (tracking_number, tracking_token, client_id, quote_id, sender_name, sender_phone,
             recipient_name, recipient_phone, recipient_address, recipient_city, recipient_department,
             latitude, longitude,
             zone_id, description, weight, length, width, height,
             volumetric_weight, chargeable_weight, declared_value, flete_cost, delivery_cost, shipping_cost,
             pricing_rule_id, delivery_type_id, delivery_option, local_delivery_type_id,
             shipping_service_id, delivery_option_id, pricing_type, status_id, assigned_to, notes, origin,
             pickup_required, pickup_vehicle_id, created_by)
             VALUES (:tracking_number, :tracking_token, :client_id, :quote_id, :sender_name, :sender_phone,
             :recipient_name, :recipient_phone, :recipient_address, :recipient_city, :recipient_department,
             :latitude, :longitude,
             :zone_id, :description, :weight, :length, :width, :height,
             :volumetric_weight, :chargeable_weight, :declared_value, :flete_cost, :delivery_cost, :shipping_cost,
             :pricing_rule_id, :delivery_type_id, :delivery_option, :local_delivery_type_id,
             :shipping_service_id, :delivery_option_id, :pricing_type, :status_id, :assigned_to, :notes, :origin,
             :pickup_required, :pickup_vehicle_id, :created_by)'
        );
        $stmt->execute([
            'tracking_number' => $data['tracking_number'],
            'tracking_token' => $data['tracking_token'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'quote_id' => $data['quote_id'] ?? null,
            'sender_name' => $data['sender_name'],
            'sender_phone' => $data['sender_phone'] ?? null,
            'recipient_name' => $data['recipient_name'],
            'recipient_phone' => $data['recipient_phone'],
            'recipient_address' => $data['recipient_address'],
            'recipient_city' => $data['recipient_city'] ?? null,
            'recipient_department' => $data['recipient_department'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'zone_id' => $data['zone_id'] ?? null,
            'description' => $data['description'] ?? null,
            'weight' => $data['weight'] ?? null,
            'length' => $data['length'] ?? null,
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'volumetric_weight' => $data['volumetric_weight'] ?? null,
            'chargeable_weight' => $data['chargeable_weight'] ?? null,
            'declared_value' => $data['declared_value'] ?? null,
            'flete_cost' => $data['flete_cost'] ?? 0.00,
            'delivery_cost' => $data['delivery_cost'] ?? 0.00,
            'shipping_cost' => $data['shipping_cost'] ?? null,
            'pricing_rule_id' => $data['pricing_rule_id'] ?? null,
            'delivery_type_id' => $data['delivery_type_id'] ?? null,
            'delivery_option' => $data['delivery_option'] ?? 'branch',
            'local_delivery_type_id' => $data['local_delivery_type_id'] ?? null,
            'shipping_service_id' => $data['shipping_service_id'] ?? null,
            'delivery_option_id' => $data['delivery_option_id'] ?? null,
            'pricing_type' => $data['pricing_type'] ?? 'weight',
            'status_id' => $data['status_id'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'notes' => $data['notes'] ?? null,
            'origin' => $data['origin'] ?? 'online',
            'pickup_required' => !empty($data['pickup_required']) ? 1 : 0,
            'pickup_vehicle_id' => $data['pickup_vehicle_id'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['client_id', 'sender_name', 'sender_phone', 'recipient_name', 'recipient_phone',
                   'recipient_address', 'recipient_city', 'recipient_department',
                   'latitude', 'longitude', 'zone_id',
                   'description',
                   'weight', 'length', 'width', 'height',
                   'volumetric_weight', 'chargeable_weight', 'declared_value',
                   'flete_cost', 'delivery_cost', 'shipping_cost',
'pricing_rule_id', 'delivery_type_id', 'delivery_option', 'local_delivery_type_id',
                    'shipping_service_id', 'delivery_option_id', 'pricing_type', 'status_id', 'assigned_to',
                    'notes', 'origin', 'received_at', 'delivered_at'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE package SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM package WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function getStatusId(string $status): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM package_status WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $status]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    public function getDeliveryTypeId(string $name): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM delivery_type WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    public function getShippingServiceId(string $name): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM shipping_service WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    public function getDeliveryOptionId(string $name): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM delivery_option WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    public function isValidTransition(string $from, string $to): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM package_status_transition WHERE from_status = :from AND to_status = :to LIMIT 1'
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        return $stmt->fetch() !== false;
    }

    public function getStatusName(int $statusId): ?string
    {
        $stmt = $this->db->prepare('SELECT name FROM package_status WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $statusId]);
        return $stmt->fetchColumn() ?: null;
    }

    /**
     * Paquetes candidatos para ser agregados a un embarque: estado received, sin
     * embarque asignado y del servicio internacional compatible con el modo del
     * embarque (air|sea). Opcionalmente filtra por query de tracking/nombre.
     */
    public function findEligibleForShipment(?string $q = null, ?string $mode = null, int $limit = 500): array
    {
        $where = ['ps.name = :status'];
        $params = ['status' => 'received'];

        $serviceJoin = ' LEFT JOIN shipping_service ss ON ss.id = p.shipping_service_id';
        if ($mode === 'air' || $mode === 'sea') {
            $serviceJoin .= ' INNER JOIN delivery_type sdt ON sdt.id = ss.delivery_type_id';
            $where[] = "sdt.category = 'international'";
            $where[] = 'sdt.name = :mode_name';
            $params['mode_name'] = $mode === 'sea' ? 'maritimo' : 'aereo';
        }

        if ($q !== null && trim($q) !== '') {
            $like = '%' . $q . '%';
            $where[] = '(p.tracking_number LIKE :q1 OR p.recipient_name LIKE :q2 OR c.name LIKE :q3)';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }

        $whereClause = implode(' AND ', $where);
        $stmt = $this->db->prepare("
            SELECT p.id, p.tracking_number, p.tracking_token, p.client_id, c.name AS client_name,
                   p.recipient_name, p.recipient_address, p.recipient_city, p.recipient_department,
                   p.zone_id, z.name AS zone_name,
                   p.description, p.weight, p.chargeable_weight,
                   p.flete_cost, p.delivery_cost, p.shipping_cost,
                   p.status_id, ps.name AS status_name, p.origin, p.received_at,
                   ss.name AS shipping_service_name
            FROM package p
            LEFT JOIN client c ON c.id = p.client_id
            LEFT JOIN package_status ps ON ps.id = p.status_id
            LEFT JOIN zone z ON z.id = p.zone_id
            {$serviceJoin}
            WHERE {$whereClause}
              AND NOT EXISTS (SELECT 1 FROM shipment_package sp WHERE sp.package_id = p.id)
            ORDER BY p.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
