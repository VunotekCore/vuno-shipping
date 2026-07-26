<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class DireccionModel
{
    public function __construct(private PDO $db) {}

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM direccion')->fetchColumn();
    }

    public function listPaginated(int $limit, int $offset): array
    {
        $stmt = $this->db->prepare("
            SELECT d.id, d.name, d.description, d.created_at,
                   COUNT(p.id) AS puesto_count
            FROM direccion d
            LEFT JOIN puesto p ON p.direccion_id = d.id
            GROUP BY d.id
            ORDER BY d.name
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listAll(): array
    {
        $stmt = $this->db->query('SELECT id, name FROM direccion ORDER BY name');
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, description, created_at FROM direccion WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $name, ?string $description): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO direccion (name, description) VALUES (:name, :description)'
        );
        $stmt->execute(['name' => $name, 'description' => $description]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $name, ?string $description): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE direccion SET name = :name, description = :description WHERE id = :id'
        );
        return $stmt->execute(['id' => $id, 'name' => $name, 'description' => $description]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM direccion WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function countPuestos(int $direccionId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM puesto WHERE direccion_id = :id');
        $stmt->execute(['id' => $direccionId]);
        return (int) $stmt->fetchColumn();
    }
}
