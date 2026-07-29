<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class DeliveryOptionModel
{
    public function __construct(private PDO $db) {}

    public function listAll(): array
    {
        $stmt = $this->db->query('SELECT id, name, description, is_active FROM delivery_option ORDER BY name ASC');
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, name, description, is_active FROM delivery_option WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByName(string $name): ?array
    {
        $stmt = $this->db->prepare('SELECT id FROM delivery_option WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO delivery_option (name, description, is_active) VALUES (:name, :description, :is_active)');
        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(array $data): void
    {
        $stmt = $this->db->prepare('UPDATE delivery_option SET name = :name, description = :description, is_active = :is_active WHERE id = :id');
        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'id' => $data['id'],
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM delivery_option WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
