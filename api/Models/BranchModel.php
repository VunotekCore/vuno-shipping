<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class BranchModel
{
    public function __construct(private PDO $db) {}

    public function listAll(?bool $activeOnly = null): array
    {
        $where = '';
        $params = [];
        if ($activeOnly === true) {
            $where = ' WHERE b.is_active = 1';
        } elseif ($activeOnly === false) {
            $where = ' WHERE b.is_active = 0';
        }

        $stmt = $this->db->prepare("
            SELECT b.*,
                   rw.name AS receiving_warehouse_name, rw.code AS receiving_warehouse_code,
                   tw.name AS transfer_to_warehouse_name, tw.code AS transfer_to_warehouse_code
            FROM branch b
            LEFT JOIN warehouse rw ON rw.id = b.receiving_warehouse_id
            LEFT JOIN warehouse tw ON tw.id = b.transfer_to_warehouse_id
            {$where}
            ORDER BY b.sort_order ASC, b.name ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT b.*,
                   rw.name AS receiving_warehouse_name, rw.code AS receiving_warehouse_code,
                   tw.name AS transfer_to_warehouse_name, tw.code AS transfer_to_warehouse_code
            FROM branch b
            LEFT JOIN warehouse rw ON rw.id = b.receiving_warehouse_id
            LEFT JOIN warehouse tw ON tw.id = b.transfer_to_warehouse_id
            WHERE b.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM branch WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO branch (code, name, country_code, type, receiving_warehouse_id, transfer_to_warehouse_id, allows_pickup, is_active, sort_order)
             VALUES (:code, :name, :country_code, :type, :receiving_warehouse_id, :transfer_to_warehouse_id, :allows_pickup, :is_active, :sort_order)'
        );
        $stmt->execute([
            'code' => $data['code'],
            'name' => $data['name'],
            'country_code' => $data['country_code'] ?? 'NI',
            'type' => $data['type'] ?? 'direct',
            'receiving_warehouse_id' => $data['receiving_warehouse_id'] ?? null,
            'transfer_to_warehouse_id' => $data['transfer_to_warehouse_id'] ?? null,
            'allows_pickup' => isset($data['allows_pickup']) ? (int) $data['allows_pickup'] : 0,
            'is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 1,
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];
        foreach (['code', 'name', 'country_code', 'type', 'receiving_warehouse_id', 'transfer_to_warehouse_id', 'allows_pickup', 'is_active', 'sort_order'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }
        if (empty($fields)) {
            return false;
        }
        $sql = 'UPDATE branch SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM branch WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}
