<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class CompanyModel
{
    public function __construct(private PDO $db) {}

    public function get(): ?array
    {
        $stmt = $this->db->query('SELECT * FROM company LIMIT 1');
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $fields = [];
        $params = [];

        foreach (['name', 'logo', 'ruc', 'address', 'phone', 'email', 'website'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = $field;
                $params[$field] = $data[$field];
            }
        }

        $columns = implode(', ', $fields);
        $placeholders = implode(', ', array_map(fn($f) => ":$f", $fields));

        $stmt = $this->db->prepare("INSERT INTO company ($columns) VALUES ($placeholders)");
        $stmt->execute($params);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['name', 'logo', 'ruc', 'address', 'phone', 'email', 'website'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE company SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}
