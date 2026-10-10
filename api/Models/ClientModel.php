<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class ClientModel
{
    public function __construct(private PDO $db) {}

    public function count(?int $agentId = null): int
    {
        if ($agentId !== null) {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM client WHERE agent_id = :agent_id');
            $stmt->execute(['agent_id' => $agentId]);
            return (int) $stmt->fetchColumn();
        }
        return (int) $this->db->query('SELECT COUNT(*) FROM client')->fetchColumn();
    }

    public function listPaginated(int $limit, int $offset, ?int $agentId = null): array
    {
        $where = $agentId !== null ? 'WHERE c.agent_id = :agent_id' : '';
        $stmt = $this->db->prepare("
            SELECT c.*,
                   u.name AS agent_name,
                   (SELECT COUNT(*) FROM package p WHERE p.client_id = c.id) AS package_count
            FROM client c
            LEFT JOIN `user` u ON u.id = c.agent_id
            $where
            ORDER BY c.name ASC
            LIMIT :limit OFFSET :offset
        ");
        if ($agentId !== null) {
            $stmt->bindValue('agent_id', $agentId, PDO::PARAM_INT);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listAll(?int $agentId = null): array
    {
        if ($agentId !== null) {
            $stmt = $this->db->prepare(
                'SELECT id, name, email, phone, ruc, is_active, agent_id FROM client WHERE is_active = 1 AND agent_id = :agent_id ORDER BY name ASC'
            );
            $stmt->execute(['agent_id' => $agentId]);
            return $stmt->fetchAll();
        }
        $stmt = $this->db->query('SELECT id, name, email, phone, ruc, is_active, agent_id FROM client WHERE is_active = 1 ORDER BY name ASC');
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM client WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO client (name, email, phone, ruc, address, department, notes, agent_id, is_active)
             VALUES (:name, :email, :phone, :ruc, :address, :department, :notes, :agent_id, :is_active)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'ruc' => $data['ruc'] ?? null,
            'address' => $data['address'] ?? null,
            'department' => $data['department'] ?? null,
            'notes' => $data['notes'] ?? null,
            'agent_id' => $data['agent_id'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['name', 'email', 'phone', 'ruc', 'address', 'department', 'notes', 'agent_id', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE client SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM client WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}
