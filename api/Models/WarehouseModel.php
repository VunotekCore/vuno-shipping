<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class WarehouseModel
{
    public function __construct(private PDO $db) {}

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM warehouse')->fetchColumn();
    }

    public function listPaginated(int $limit, int $offset): array
    {
        $stmt = $this->db->prepare("
            SELECT w.*,
                   (SELECT COUNT(*) FROM storage_location sl WHERE sl.warehouse_id = w.id) AS location_count
            FROM warehouse w
            ORDER BY w.name ASC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listAll(): array
    {
        $stmt = $this->db->query('SELECT id, name, code, origin, is_active FROM warehouse WHERE is_active = 1 ORDER BY name ASC');
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM warehouse WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM warehouse WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByOrigin(string $origin): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM warehouse WHERE origin = :origin AND is_active = 1 ORDER BY id ASC LIMIT 1');
        $stmt->execute(['origin' => $origin]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function existsCode(string $code, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM warehouse WHERE code = :code';
        $params = ['code' => $code];
        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $params['id'] = $excludeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO warehouse (name, code, origin, address, is_active)
             VALUES (:name, :code, :origin, :address, :is_active)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'code' => $data['code'],
            'origin' => $data['origin'] ?? 'online',
            'address' => $data['address'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['name', 'code', 'origin', 'address', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE warehouse SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM warehouse WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function countLocations(int $id): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM storage_location WHERE warehouse_id = :id');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn();
    }
}
