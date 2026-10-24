<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class DeliveryTypeModel
{
    public function __construct(private PDO $db) {}

    private function hasFrequencyColumn(): bool
    {
        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM delivery_type LIKE 'delivery_frequency'");
            return $stmt !== false && $stmt->fetch() !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function listAll(): array
    {
        if ($this->hasFrequencyColumn()) {
            $stmt = $this->db->query('SELECT id, name, description, delivery_frequency, category FROM delivery_type ORDER BY name ASC');
            return $stmt->fetchAll();
        }
        $stmt = $this->db->query('SELECT id, name, description, NULL AS delivery_frequency, category FROM delivery_type ORDER BY name ASC');
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        if ($this->hasFrequencyColumn()) {
            $stmt = $this->db->prepare('SELECT id, name, description, delivery_frequency, category FROM delivery_type WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();
            return $row ?: null;
        }
        $stmt = $this->db->prepare('SELECT id, name, description, NULL AS delivery_frequency, category FROM delivery_type WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        if ($this->hasFrequencyColumn()) {
            $stmt = $this->db->prepare('INSERT INTO delivery_type (name, description, delivery_frequency, category) VALUES (:name, :description, :delivery_frequency, :category)');
            $stmt->execute([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'delivery_frequency' => $data['delivery_frequency'] ?? null,
                'category' => $data['category'] ?? 'international',
            ]);
            return (int) $this->db->lastInsertId();
        }
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
        if ($this->hasFrequencyColumn()) {
            $stmt = $this->db->prepare('UPDATE delivery_type SET name = :name, description = :description, delivery_frequency = :delivery_frequency, category = :category WHERE id = :id');
            $stmt->execute([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'delivery_frequency' => $data['delivery_frequency'] ?? null,
                'category' => $data['category'] ?? 'international',
                'id' => $data['id'],
            ]);
            return;
        }
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
