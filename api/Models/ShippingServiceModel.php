<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class ShippingServiceModel
{
    public function __construct(private PDO $db) {}

    public function listAll(): array
    {
        $stmt = $this->db->query('SELECT ss.id, ss.name, ss.description, ss.is_active, ss.delivery_type_id, dt.name AS delivery_type_name FROM shipping_service ss LEFT JOIN delivery_type dt ON dt.id = ss.delivery_type_id ORDER BY ss.name ASC');
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT ss.id, ss.name, ss.description, ss.is_active, ss.delivery_type_id, dt.name AS delivery_type_name FROM shipping_service ss LEFT JOIN delivery_type dt ON dt.id = ss.delivery_type_id WHERE ss.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO shipping_service (name, description, is_active, delivery_type_id) VALUES (:name, :description, :is_active, :delivery_type_id)');
        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'delivery_type_id' => $data['delivery_type_id'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(array $data): void
    {
        $stmt = $this->db->prepare('UPDATE shipping_service SET name = :name, description = :description, is_active = :is_active, delivery_type_id = :delivery_type_id WHERE id = :id');
        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'delivery_type_id' => $data['delivery_type_id'] ?? null,
            'id' => $data['id'],
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM shipping_service WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
