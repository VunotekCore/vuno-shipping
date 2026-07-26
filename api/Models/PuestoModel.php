<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class PuestoModel
{
    public function __construct(private PDO $db) {}

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM puesto')->fetchColumn();
    }

    public function listPaginated(int $limit, int $offset): array
    {
        $stmt = $this->db->prepare("
            SELECT p.id, p.name, p.description, p.direccion_id, d.name AS direccion_name, p.created_at,
                   COUNT(e.id) AS empleado_count
            FROM puesto p
            JOIN direccion d ON d.id = p.direccion_id
            LEFT JOIN empleado e ON e.puesto_id = p.id
            GROUP BY p.id
            ORDER BY d.name, p.name
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listAll(): array
    {
        $stmt = $this->db->query("
            SELECT p.id, p.name, d.name AS direccion_name
            FROM puesto p
            JOIN direccion d ON d.id = p.direccion_id
            ORDER BY d.name, p.name
        ");
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.id, p.name, p.description, p.direccion_id, d.name AS direccion_name, p.created_at
            FROM puesto p
            JOIN direccion d ON d.id = p.direccion_id
            WHERE p.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $name, ?string $description, int $direccionId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO puesto (name, description, direccion_id) VALUES (:name, :description, :direccion_id)'
        );
        $stmt->execute(['name' => $name, 'description' => $description, 'direccion_id' => $direccionId]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $name, ?string $description, int $direccionId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE puesto SET name = :name, description = :description, direccion_id = :direccion_id WHERE id = :id'
        );
        return $stmt->execute(['id' => $id, 'name' => $name, 'description' => $description, 'direccion_id' => $direccionId]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM puesto WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function countEmpleados(int $puestoId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM empleado WHERE puesto_id = :id');
        $stmt->execute(['id' => $puestoId]);
        return (int) $stmt->fetchColumn();
    }
}
