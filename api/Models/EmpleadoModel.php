<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class EmpleadoModel
{
    public function __construct(private PDO $db) {}

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM empleado')->fetchColumn();
    }

    public function listPaginated(int $limit, int $offset): array
    {
        $stmt = $this->db->prepare("
            SELECT e.id, e.name, e.email, e.phone, e.cedula, e.fecha_ingreso,
                   e.puesto_id, p.name AS puesto_name,
                   e.user_id, u.email AS user_email, u.name AS user_name,
                   e.is_active, e.created_at, e.updated_at
            FROM empleado e
            JOIN puesto p ON p.id = e.puesto_id
            LEFT JOIN `user` u ON u.id = e.user_id
            ORDER BY e.name
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT e.id, e.name, e.email, e.phone, e.cedula, e.fecha_ingreso,
                   e.puesto_id, p.name AS puesto_name,
                   e.user_id, u.email AS user_email, u.name AS user_name,
                   e.is_active, e.created_at, e.updated_at
            FROM empleado e
            JOIN puesto p ON p.id = e.puesto_id
            LEFT JOIN `user` u ON u.id = e.user_id
            WHERE e.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO empleado (name, email, phone, cedula, fecha_ingreso, puesto_id, user_id, is_active)
            VALUES (:name, :email, :phone, :cedula, :fecha_ingreso, :puesto_id, :user_id, :is_active)
        ");
        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'cedula' => $data['cedula'] ?? null,
            'fecha_ingreso' => $data['fecha_ingreso'] ?? null,
            'puesto_id' => $data['puesto_id'],
            'user_id' => $data['user_id'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['name', 'email', 'phone', 'cedula', 'fecha_ingreso', 'puesto_id', 'user_id', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE empleado SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM empleado WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function findByCedula(string $cedula, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT id, cedula FROM empleado WHERE cedula = :cedula';
        $params = ['cedula' => $cedula];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByUserId(int $userId, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT id, user_id FROM empleado WHERE user_id = :user_id';
        $params = ['user_id' => $userId];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
