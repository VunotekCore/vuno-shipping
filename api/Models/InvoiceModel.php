<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class InvoiceModel
{
    public function __construct(private PDO $db) {}

    public function count(array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM invoice i
            INNER JOIN package p ON p.id = i.package_id
            LEFT JOIN client c ON c.id = i.client_id
            $where
        ");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function listPaginated(int $limit, int $offset, array $filters = []): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $stmt = $this->db->prepare("
            SELECT i.*,
                   p.tracking_number,
                   c.name AS client_name_ref,
                   pm.name AS payment_method_name
            FROM invoice i
            INNER JOIN package p ON p.id = i.package_id
            LEFT JOIN client c ON c.id = i.client_id
            LEFT JOIN payment_method pm ON pm.id = i.payment_method_id
            $where
            ORDER BY i.created_at DESC
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
            SELECT i.*,
                   p.tracking_number,
                   p.recipient_name,
                   p.recipient_phone,
                   p.recipient_address,
                   p.recipient_city,
                   p.recipient_department,
                   p.description,
                   p.weight,
                   p.length,
                   p.width,
                   p.height,
                   p.volumetric_weight,
                   p.chargeable_weight,
                   p.declared_value,
                   p.shipping_cost,
                   p.delivery_cost,
                   p.flete_cost,
                   c.name AS client_name_ref,
                   pm.name AS payment_method_name
            FROM invoice i
            INNER JOIN package p ON p.id = i.package_id
            LEFT JOIN client c ON c.id = i.client_id
            LEFT JOIN payment_method pm ON pm.id = i.payment_method_id
            WHERE i.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByPackageId(int $packageId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM invoice WHERE package_id = :pid ORDER BY id DESC LIMIT 1');
        $stmt->execute(['pid' => $packageId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO invoice (invoice_number, package_id, client_id, client_name, client_ruc,
                                  base_amount, tax_rate, tax_amount, total_amount, currency,
                                  exchange_rate, created_by)
             VALUES (:invoice_number, :package_id, :client_id, :client_name, :client_ruc,
                     :base_amount, :tax_rate, :tax_amount, :total_amount, :currency,
                     :exchange_rate, :created_by)'
        );
        $stmt->execute([
            'invoice_number' => $data['invoice_number'],
            'package_id' => $data['package_id'],
            'client_id' => $data['client_id'] ?? null,
            'client_name' => $data['client_name'] ?? null,
            'client_ruc' => $data['client_ruc'] ?? null,
            'base_amount' => $data['base_amount'],
            'tax_rate' => $data['tax_rate'],
            'tax_amount' => $data['tax_amount'],
            'total_amount' => $data['total_amount'],
            'currency' => $data['currency'] ?? 'USD',
            'exchange_rate' => $data['exchange_rate'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function markPaid(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE invoice SET status = :status, paid_amount = :paid_amount,
                    paid_currency = :paid_currency, payment_method_id = :payment_method_id,
                    payment_reference = :payment_reference, exchange_rate = :exchange_rate,
                    paid_at = NOW(), notes = COALESCE(NULLIF(:notes, ""), notes)
             WHERE id = :id'
        );
        return $stmt->execute([
            'status' => 'paid',
            'paid_amount' => $data['paid_amount'],
            'paid_currency' => $data['paid_currency'],
            'payment_method_id' => $data['payment_method_id'],
            'payment_reference' => $data['payment_reference'] ?? null,
            'exchange_rate' => $data['exchange_rate'] ?? null,
            'notes' => $data['notes'] ?? null,
            'id' => $id,
        ]);
    }

    public function cancel(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE invoice SET status = 'cancelled' WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function sumByStatus(string $status): float
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(total_amount), 0) FROM invoice WHERE status = :status'
        );
        $stmt->execute(['status' => $status]);
        return (float) $stmt->fetchColumn();
    }

    public function countPaidToday(): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM invoice WHERE status = 'paid' AND DATE(paid_at) = CURDATE()"
        );
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Total cobrado hoy separado por moneda. `paid_amount` esta expresado en la
     * moneda en que se cobro (`paid_currency`), asi que sumarlo sin agrupar
     * mezclaria dolares y cordobas en un mismo escalar. Siempre devuelve las
     * dos claves para que el frontend no tenga que completar defaults.
     *
     * @return array{USD: float, CORD: float}
     */
    public function sumPaidTodayByCurrency(): array
    {
        $stmt = $this->db->prepare(
            "SELECT paid_currency, COALESCE(SUM(paid_amount), 0) AS total
             FROM invoice
             WHERE status = 'paid' AND DATE(paid_at) = CURDATE() AND paid_currency IS NOT NULL
             GROUP BY paid_currency"
        );
        $stmt->execute();

        $totals = ['USD' => 0.0, 'CORD' => 0.0];
        while ($row = $stmt->fetch()) {
            $currency = $row['paid_currency'] === 'CORD' ? 'CORD' : 'USD';
            $totals[$currency] += (float) $row['total'];
        }

        return $totals;
    }

    private function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'i.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['client_id'])) {
            $where[] = 'i.client_id = :client_id';
            $params['client_id'] = (int) $filters['client_id'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'DATE(i.created_at) >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'DATE(i.created_at) <= :to';
            $params['to'] = $filters['to'];
        }

        $sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        return [$sql, $params];
    }
}