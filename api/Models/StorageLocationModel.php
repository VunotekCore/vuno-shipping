<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class StorageLocationModel
{
    public function __construct(private PDO $db) {}

    public function count(?int $warehouseId = null): int
    {
        if ($warehouseId !== null) {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM storage_location WHERE warehouse_id = :w');
            $stmt->execute(['w' => $warehouseId]);
            return (int) $stmt->fetchColumn();
        }
        return (int) $this->db->query('SELECT COUNT(*) FROM storage_location')->fetchColumn();
    }

    public function listPaginated(int $limit, int $offset, ?int $warehouseId = null): array
    {
        $where = '';
        $params = [];
        if ($warehouseId !== null) {
            $where = 'WHERE sl.warehouse_id = :w';
            $params['w'] = $warehouseId;
        }

        $stmt = $this->db->prepare("
            SELECT sl.*,
                   w.name AS warehouse_name, w.code AS warehouse_code,
                   sc.name AS category_name, sc.color AS category_color,
                   (SELECT COUNT(*) FROM package p WHERE p.storage_location_id = sl.id) AS occupied
            FROM storage_location sl
            LEFT JOIN warehouse w ON w.id = sl.warehouse_id
            LEFT JOIN storage_category sc ON sc.id = sl.category_id
            {$where}
            ORDER BY w.name ASC, sl.code ASC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listAll(?int $warehouseId = null): array
    {
        $where = 'WHERE sl.is_active = 1';
        $params = [];
        if ($warehouseId !== null) {
            $where .= ' AND sl.warehouse_id = :w';
            $params['w'] = $warehouseId;
        }

        $stmt = $this->db->prepare("
            SELECT sl.id, sl.code, sl.warehouse_id, sl.category_id, sl.capacity,
                   sc.name AS category_name,
                   (SELECT COUNT(*) FROM package p WHERE p.storage_location_id = sl.id) AS occupied
            FROM storage_location sl
            LEFT JOIN storage_category sc ON sc.id = sl.category_id
            {$where}
            ORDER BY sl.code ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Ubicaciones disponibles de una bodega para una clasificacion concreta:
     * primero las que coinciden con la clasificacion, luego las genericas.
     * Ordenadas por ocupacion ascendente (para repartir carga) y codigo.
     */
    public function findByWarehouse(int $warehouseId, ?int $categoryId = null): array
    {
        $params = ['w' => $warehouseId];
        $catFilter = '';
        $orderPrefer = '';
        if ($categoryId !== null) {
            $catFilter = 'AND (sl.category_id = :c OR sl.category_id IS NULL)';
            $params['c'] = $categoryId;
            $orderPrefer = '(sl.category_id = :c2) DESC, ';
            $params['c2'] = $categoryId;
        }

        $stmt = $this->db->prepare("
            SELECT sl.*,
                   sc.name AS category_name, sc.color AS category_color,
                   (SELECT COUNT(*) FROM package p WHERE p.storage_location_id = sl.id) AS occupied
            FROM storage_location sl
            LEFT JOIN storage_category sc ON sc.id = sl.category_id
            WHERE sl.warehouse_id = :w AND sl.is_active = 1
              {$catFilter}
            ORDER BY {$orderPrefer}occupied ASC, sl.code ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT sl.*, w.name AS warehouse_name, w.code AS warehouse_code,
                   sc.name AS category_name, sc.color AS category_color,
                   (SELECT COUNT(*) FROM package p WHERE p.storage_location_id = sl.id) AS occupied
            FROM storage_location sl
            LEFT JOIN warehouse w ON w.id = sl.warehouse_id
            LEFT JOIN storage_category sc ON sc.id = sl.category_id
            WHERE sl.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function existsCode(int $warehouseId, string $code, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM storage_location WHERE warehouse_id = :w AND code = :code';
        $params = ['w' => $warehouseId, 'code' => $code];
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
            'INSERT INTO storage_location (warehouse_id, category_id, aisle, rack, level, position, code, capacity, is_active)
             VALUES (:warehouse_id, :category_id, :aisle, :rack, :level, :position, :code, :capacity, :is_active)'
        );
        $stmt->execute([
            'warehouse_id' => $data['warehouse_id'],
            'category_id' => $data['category_id'] ?? null,
            'aisle' => $data['aisle'] ?? null,
            'rack' => $data['rack'] ?? null,
            'level' => $data['level'] ?? null,
            'position' => $data['position'] ?? null,
            'code' => $data['code'],
            'capacity' => $data['capacity'] ?? 1,
            'is_active' => $data['is_active'] ?? 1,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['warehouse_id', 'category_id', 'aisle', 'rack', 'level', 'position', 'code', 'capacity', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE storage_location SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM storage_location WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function countPackages(int $id): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM package WHERE storage_location_id = :id');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Genera una grilla de ubicaciones (levels x positions) para una bodega.
     * Ignora las que ya existan (mismo code). Devuelve cuantos se crearon.
     */
    public function generate(int $warehouseId, string $aisle, string $rack, int $levels, int $positions, int $capacity, ?int $categoryId): int
    {
        $created = 0;
        for ($l = 1; $l <= $levels; $l++) {
            for ($p = 1; $p <= $positions; $p++) {
                $level = str_pad((string) $l, 2, '0', STR_PAD_LEFT);
                $pos = str_pad((string) $p, 2, '0', STR_PAD_LEFT);
                $code = strtoupper($aisle) . '-' . strtoupper($rack) . '-' . $level . '-' . $pos;
                if ($this->existsCode($warehouseId, $code)) {
                    continue;
                }
                $this->create([
                    'warehouse_id' => $warehouseId,
                    'category_id' => $categoryId,
                    'aisle' => strtoupper($aisle),
                    'rack' => strtoupper($rack),
                    'level' => $level,
                    'position' => $pos,
                    'code' => $code,
                    'capacity' => $capacity,
                ]);
                $created++;
            }
        }
        return $created;
    }
}
