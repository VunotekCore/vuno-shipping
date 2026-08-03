<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class VehicleModel
{
    public function __construct(private PDO $db) {}

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM vehicle')->fetchColumn();
    }

    public function listPaginated(int $limit, int $offset): array
    {
        $stmt = $this->db->prepare("
            SELECT v.*,
                   vs.name AS status_name,
                   u.name AS driver_name
            FROM vehicle v
            LEFT JOIN vehicle_status vs ON vs.id = v.status_id
            LEFT JOIN `user` u ON u.id = v.current_driver_id
            ORDER BY v.plate ASC
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
            SELECT v.id, v.plate, v.brand, v.model, vs.name AS status_name
            FROM vehicle v
            LEFT JOIN vehicle_status vs ON vs.id = v.status_id
            WHERE v.status_id IS NULL
               OR v.status_id = (SELECT id FROM vehicle_status WHERE name = 'active' LIMIT 1)
            ORDER BY v.plate ASC
        ");
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT v.*,
                   vs.name AS status_name,
                   u.name AS driver_name
            FROM vehicle v
            LEFT JOIN vehicle_status vs ON vs.id = v.status_id
            LEFT JOIN `user` u ON u.id = v.current_driver_id
            WHERE v.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO vehicle (plate, brand, model, year, color, fuel_type, capacity_kg, capacity_m3, current_driver_id, status_id, notes)
             VALUES (:plate, :brand, :model, :year, :color, :fuel_type, :capacity_kg, :capacity_m3, :current_driver_id, :status_id, :notes)'
        );
        $stmt->execute([
            'plate' => $data['plate'],
            'brand' => $data['brand'] ?? null,
            'model' => $data['model'] ?? null,
            'year' => $data['year'] ?? null,
            'color' => $data['color'] ?? null,
            'fuel_type' => $data['fuel_type'] ?? null,
            'capacity_kg' => $data['capacity_kg'] ?? null,
            'capacity_m3' => $data['capacity_m3'] ?? null,
            'current_driver_id' => $data['current_driver_id'] ?? null,
            'status_id' => $data['status_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['plate', 'brand', 'model', 'year', 'color', 'fuel_type', 'capacity_kg', 'capacity_m3', 'current_driver_id', 'status_id', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE vehicle SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM vehicle WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function validateStatusExists(?int $statusId): bool
    {
        if ($statusId === null) {
            return true;
        }
        $stmt = $this->db->prepare('SELECT 1 FROM vehicle_status WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $statusId]);
        return $stmt->fetch() !== false;
    }

    public function validateDriverExists(?int $driverId): bool
    {
        if ($driverId === null) {
            return true;
        }
        $stmt = $this->db->prepare('SELECT 1 FROM `user` WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $driverId]);
        return $stmt->fetch() !== false;
    }
}
