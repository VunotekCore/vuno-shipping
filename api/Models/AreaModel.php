<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class AreaModel
{
    public function __construct(private PDO $db) {}

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM area')->fetchColumn();
    }

    public function listPaginated(int $limit, int $offset): array
    {
        $stmt = $this->db->prepare("
            SELECT a.id, a.name, a.description, a.created_at,
                   COUNT(p.id) AS puesto_count
            FROM area a
            LEFT JOIN puesto p ON p.area_id = a.id
            GROUP BY a.id
            ORDER BY a.name
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listAll(): array
    {
        $stmt = $this->db->query('SELECT id, name FROM area ORDER BY name');
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, description, created_at FROM area WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $name, ?string $description): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO area (name, description) VALUES (:name, :description)'
        );
        $stmt->execute(['name' => $name, 'description' => $description]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $name, ?string $description): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE area SET name = :name, description = :description WHERE id = :id'
        );
        return $stmt->execute(['id' => $id, 'name' => $name, 'description' => $description]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM area WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function countPuestos(int $areaId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM puesto WHERE area_id = :id');
        $stmt->execute(['id' => $areaId]);
        return (int) $stmt->fetchColumn();
    }
}
