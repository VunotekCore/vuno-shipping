<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class DeliveryTypeModel
{
    public function __construct(private PDO $db) {}

    public function listAll(): array
    {
        $stmt = $this->db->query('SELECT id, name, description, category FROM delivery_type ORDER BY name ASC');
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, name, description, category FROM delivery_type WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO delivery_type (name, description, category) VALUES (:name, :description, :category)');
        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? 'international',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(array $data): void
    {
        $stmt = $this->db->prepare('UPDATE delivery_type SET name = :name, description = :description, category = :category WHERE id = :id');
        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? 'international',
            'id' => $data['id'],
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM delivery_type WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
