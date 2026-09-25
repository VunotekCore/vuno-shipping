<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class ShipmentModel
{
    public function __construct(private PDO $db) {}

    public function count(?string $status = null): int
    {
        $where = '';
        $params = [];
        if ($status !== null) {
            $where = ' WHERE s.status = :status';
            $params['status'] = $status;
        }
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM shipment s{$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function listPaginated(int $limit, int $offset, ?string $status = null): array
    {
        $where = '';
        $params = [];
        if ($status !== null) {
            $where = ' WHERE s.status = :status';
            $params['status'] = $status;
        }

        $stmt = $this->db->prepare("
            SELECT s.*, u.name AS created_by_name,
                   (SELECT COUNT(*) FROM shipment_package sp WHERE sp.shipment_id = s.id) AS package_count
            FROM shipment s
            LEFT JOIN `user` u ON u.id = s.created_by
            {$where}
            ORDER BY s.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT s.*, u.name AS created_by_name,
                   (SELECT COUNT(*) FROM shipment_package sp WHERE sp.shipment_id = s.id) AS package_count
            FROM shipment s
            LEFT JOIN `user` u ON u.id = s.created_by
            WHERE s.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listPackages(int $shipmentId): array
    {
        $stmt = $this->db->prepare("
            SELECT p.id, p.tracking_number, p.tracking_token, p.client_id, c.name AS client_name,
                   c.email AS client_email,
                   p.recipient_name, p.recipient_address, p.recipient_city, p.recipient_department,
                   p.zone_id, z.name AS zone_name,
                   p.description, p.weight, p.chargeable_weight,
                   p.flete_cost, p.delivery_cost, p.shipping_cost,
                   p.status_id, ps.name AS status_name, p.origin, p.received_at
            FROM shipment_package sp
            JOIN package p ON p.id = sp.package_id
            LEFT JOIN client c ON c.id = p.client_id
            LEFT JOIN zone z ON z.id = p.zone_id
            LEFT JOIN package_status ps ON ps.id = p.status_id
            WHERE sp.shipment_id = :shipment_id
            ORDER BY p.tracking_number ASC
        ");
        $stmt->execute(['shipment_id' => $shipmentId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO shipment (code, mode, status, departure_date, arrival_date, notes, created_by)
             VALUES (:code, :mode, :status, :departure_date, :arrival_date, :notes, :created_by)'
        );
        $stmt->execute([
            'code' => $data['code'],
            'mode' => $data['mode'] ?? 'air',
            'status' => $data['status'] ?? 'draft',
            'departure_date' => $data['departure_date'] ?? null,
            'arrival_date' => $data['arrival_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['code', 'mode', 'status', 'departure_date', 'arrival_date', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE shipment SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM shipment WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function addPackage(int $shipmentId, int $packageId): bool
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO shipment_package (shipment_id, package_id) VALUES (:sid, :pid)'
        );
        return $stmt->execute(['sid' => $shipmentId, 'pid' => $packageId]);
    }

    public function removePackage(int $shipmentId, int $packageId): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM shipment_package WHERE shipment_id = :sid AND package_id = :pid'
        );
        return $stmt->execute(['sid' => $shipmentId, 'pid' => $packageId]);
    }

    public function nextCode(string $prefix = 'EMB'): string
    {
        $stmt = $this->db->prepare("
            SELECT code FROM shipment
            WHERE code LIKE :like
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute(['like' => $prefix . '-%']);
        $last = $stmt->fetchColumn();

        if ($last === false || $last === null) {
            return $prefix . '-0001';
        }

        $num = (int) str_replace($prefix . '-', '', (string) $last);
        return $prefix . '-' . str_pad((string) ($num + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Todos los paquetes del embarque comparten el mismo estado de flujo (ej.
     * received antes de despachar). Devuelve el nombre de estado si es uniforme
     * o null si hay mezcla.
     */
    public function getUniformPackageStatus(int $shipmentId): ?string
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT ps.name) AS cnt,
                   MIN(ps.name) AS first_status
            FROM shipment_package sp
            JOIN package p ON p.id = sp.package_id
            LEFT JOIN package_status ps ON ps.id = p.status_id
            WHERE sp.shipment_id = :sid
        ");
        $stmt->execute(['sid' => $shipmentId]);
        $row = $stmt->fetch();
        if ($row === false || (int) $row['cnt'] !== 1) {
            return null;
        }
        return $row['first_status'] !== null ? (string) $row['first_status'] : null;
    }
}