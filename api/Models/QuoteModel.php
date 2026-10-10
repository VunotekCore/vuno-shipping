<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class QuoteModel
{
    public function __construct(private PDO $db) {}

    public function count(array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM quote q $where");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function listPaginated(int $limit, int $offset, array $filters = []): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $stmt = $this->db->prepare("
            SELECT q.*,
                   u.name AS agent_name,
                   c.name AS client_name_ref,
                   v.plate AS pickup_vehicle_plate,
                   (SELECT COUNT(*) FROM quote_item qi WHERE qi.quote_id = q.id) AS item_count,
                   (SELECT COUNT(*) FROM package p WHERE p.quote_id = q.id) AS package_count
            FROM quote q
            LEFT JOIN `user` u ON u.id = q.agent_id
            LEFT JOIN client c ON c.id = q.client_id
            LEFT JOIN vehicle v ON v.id = q.pickup_vehicle_id
            $where
            ORDER BY q.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT q.*,
                   u.name AS agent_name,
                   u.commission_type AS agent_commission_type,
                   u.commission_value AS agent_commission_value,
                   c.name AS client_name_ref,
                   v.plate AS pickup_vehicle_plate
            FROM quote q
            LEFT JOIN `user` u ON u.id = q.agent_id
            LEFT JOIN client c ON c.id = q.client_id
            LEFT JOIN vehicle v ON v.id = q.pickup_vehicle_id
            WHERE q.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Paquetes generados a partir de esta cotización (con su factura).
     */
    public function listPackages(int $quoteId): array
    {
        $stmt = $this->db->prepare("
            SELECT p.id, p.tracking_number, p.status_id, p.shipping_cost, p.created_at,
                   i.id AS invoice_id, i.invoice_number, i.status AS invoice_status
            FROM package p
            LEFT JOIN invoice i ON i.package_id = p.id
            WHERE p.quote_id = :q
            ORDER BY p.id ASC
        ");
        $stmt->execute(['q' => $quoteId]);
        return $stmt->fetchAll();
    }

    public function nextNumber(string $code): string
    {
        $seq = new DocumentSequenceModel($this->db);
        return $seq->nextNumber($code);
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO quote
                (quote_number, client_id, recipient_name, recipient_phone, recipient_email,
                 recipient_address, recipient_city, recipient_department, recipient_ruc,
                 latitude, longitude,
                 agent_id, status, origin, zone_id, delivery_type_id, shipping_service_id,
                 delivery_option, delivery_option_id, pickup_required, pickup_vehicle_id,
                 subtotal, delivery_total, base_amount, tax_rate, tax_amount, total_amount, currency,
                 valid_until, notes, created_by)
             VALUES
                (:quote_number, :client_id, :recipient_name, :recipient_phone, :recipient_email,
                 :recipient_address, :recipient_city, :recipient_department, :recipient_ruc,
                 :latitude, :longitude,
                 :agent_id, :status, :origin, :zone_id, :delivery_type_id, :shipping_service_id,
                 :delivery_option, :delivery_option_id, :pickup_required, :pickup_vehicle_id,
                 :subtotal, :delivery_total, :base_amount, :tax_rate, :tax_amount, :total_amount, :currency,
                 :valid_until, :notes, :created_by)'
        );
        $stmt->execute([
            'quote_number' => $data['quote_number'],
            'client_id' => $data['client_id'] ?? null,
            'recipient_name' => $data['recipient_name'],
            'recipient_phone' => $data['recipient_phone'] ?? null,
            'recipient_email' => $data['recipient_email'] ?? null,
            'recipient_address' => $data['recipient_address'] ?? null,
            'recipient_city' => $data['recipient_city'] ?? null,
            'recipient_department' => $data['recipient_department'] ?? null,
            'recipient_ruc' => $data['recipient_ruc'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'agent_id' => $data['agent_id'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'origin' => $data['origin'] ?? 'online',
            'zone_id' => $data['zone_id'] ?? null,
            'delivery_type_id' => $data['delivery_type_id'] ?? null,
            'shipping_service_id' => $data['shipping_service_id'] ?? null,
            'delivery_option' => $data['delivery_option'] ?? null,
            'delivery_option_id' => $data['delivery_option_id'] ?? null,
            'pickup_required' => !empty($data['pickup_required']) ? 1 : 0,
            'pickup_vehicle_id' => $data['pickup_vehicle_id'] ?? null,
            'subtotal' => $data['subtotal'] ?? 0,
            'delivery_total' => $data['delivery_total'] ?? 0,
            'base_amount' => $data['base_amount'] ?? 0,
            'tax_rate' => $data['tax_rate'] ?? 0,
            'tax_amount' => $data['tax_amount'] ?? 0,
            'total_amount' => $data['total_amount'] ?? 0,
            'currency' => $data['currency'] ?? 'USD',
            'valid_until' => $data['valid_until'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        $columns = [
            'client_id', 'recipient_name', 'recipient_phone', 'recipient_email',
            'recipient_address', 'recipient_city', 'recipient_department', 'recipient_ruc',
            'latitude', 'longitude',
            'agent_id', 'status', 'origin', 'zone_id', 'delivery_type_id', 'shipping_service_id',
            'delivery_option', 'delivery_option_id', 'pickup_required', 'pickup_vehicle_id',
            'subtotal', 'delivery_total', 'base_amount', 'tax_rate', 'tax_amount', 'total_amount',
            'currency', 'valid_until', 'notes',
        ];

        foreach ($columns as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE quote SET ' . implode(', ', $fields) . ' WHERE id = :id';
        return $this->db->prepare($sql)->execute($params);
    }

    public function markConverted(int $id, ?string $commissionType, ?float $commissionValue, ?float $commissionAmount): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE quote
             SET status = 'converted', converted_at = NOW(),
                 commission_type = :ctype, commission_value = :cvalue, commission_amount = :camount
             WHERE id = :id"
        );
        return $stmt->execute([
            'ctype' => $commissionType,
            'cvalue' => $commissionValue,
            'camount' => $commissionAmount,
            'id' => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM quote WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Marca como expiradas las cotizaciones vigentes cuya validez ya venció.
     * Se ejecuta de forma perezosa (lazy) al listar/consultar.
     */
    public function expireOverdue(): int
    {
        $stmt = $this->db->prepare(
            "UPDATE quote
             SET status = 'expired'
             WHERE status IN ('draft', 'sent')
               AND valid_until IS NOT NULL
               AND valid_until < CURDATE()"
        );
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function getAgentCommission(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT commission_type, commission_value FROM `user` WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * KPIs de comisiones por agente en un rango de fechas (por converted_at).
     *
     * @return array<int, array{agent_id: int|null, agent_name: ?string, converted_count: int, converted_total: float, commission_total: float}>
     */
    public function commissionSummary(?int $agentId, string $from, string $to): array
    {
        $where = ["q.status = 'converted'", 'DATE(q.converted_at) >= :from', 'DATE(q.converted_at) <= :to'];
        $params = ['from' => $from, 'to' => $to];

        if ($agentId !== null) {
            $where[] = 'q.agent_id = :agent_id';
            $params['agent_id'] = $agentId;
        }

        $sql = "
            SELECT q.agent_id,
                   u.name AS agent_name,
                   COUNT(*) AS converted_count,
                   COALESCE(SUM(q.total_amount), 0) AS converted_total,
                   COALESCE(SUM(q.commission_amount), 0) AS commission_total
            FROM quote q
            LEFT JOIN `user` u ON u.id = q.agent_id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY q.agent_id, u.name
            ORDER BY commission_total DESC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countByStatus(?int $agentId = null, ?string $from = null, ?string $to = null): array
    {
        $where = [];
        $params = [];
        if ($agentId !== null) {
            $where[] = 'agent_id = :agent_id';
            $params['agent_id'] = $agentId;
        }
        if ($from !== null && $from !== '') {
            $where[] = 'DATE(created_at) >= :from';
            $params['from'] = $from;
        }
        if ($to !== null && $to !== '') {
            $where[] = 'DATE(created_at) <= :to';
            $params['to'] = $to;
        }
        $sql = 'SELECT status, COUNT(*) AS total FROM quote';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' GROUP BY status';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $counts = ['draft' => 0, 'sent' => 0, 'accepted' => 0, 'rejected' => 0, 'expired' => 0, 'converted' => 0, 'cancelled' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }
        return $counts;
    }

    private function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'q.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['agent_id'])) {
            $where[] = 'q.agent_id = :agent_id';
            $params['agent_id'] = (int) $filters['agent_id'];
        }
        if (!empty($filters['client_id'])) {
            $where[] = 'q.client_id = :client_id';
            $params['client_id'] = (int) $filters['client_id'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(q.quote_number LIKE :search OR q.recipient_name LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['from'])) {
            $where[] = 'DATE(q.created_at) >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'DATE(q.created_at) <= :to';
            $params['to'] = $filters['to'];
        }

        $sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        return [$sql, $params];
    }
}
