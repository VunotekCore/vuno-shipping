<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class RoleModel
{
    public function __construct(private PDO $db) {}

    public function list(): array
    {
        $stmt = $this->db->query("
            SELECT r.id, r.name, r.slug, r.created_at,
                   COUNT(DISTINCT rp.permission_id) AS permission_count,
                   COUNT(DISTINCT u.id) AS user_count
            FROM role r
            LEFT JOIN role_permission rp ON rp.role_id = r.id
            LEFT JOIN `user` u ON u.role_id = r.id
            GROUP BY r.id
            ORDER BY r.name
        ");
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, slug, created_at FROM role WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, slug FROM role WHERE slug = :slug LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $name, string $slug): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO role (name, slug) VALUES (:name, :slug)'
        );
        $stmt->execute(['name' => $name, 'slug' => $slug]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $name, string $slug): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE role SET name = :name, slug = :slug WHERE id = :id'
        );
        return $stmt->execute(['id' => $id, 'name' => $name, 'slug' => $slug]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM role WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function countUsers(int $roleId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM `user` WHERE role_id = :id'
        );
        $stmt->execute(['id' => $roleId]);
        return (int) $stmt->fetchColumn();
    }

    public function getPermissions(int $roleId): array
    {
        $stmt = $this->db->prepare("
            SELECT p.id, p.module, p.action, p.label
            FROM role_permission rp
            JOIN permission p ON p.id = rp.permission_id
            WHERE rp.role_id = :role_id
            ORDER BY p.module, p.action
        ");
        $stmt->execute(['role_id' => $roleId]);
        return $stmt->fetchAll();
    }

    public function setPermissions(int $roleId, array $permissionIds): void
    {
        $this->db->beginTransaction();

        try {
            $this->db->prepare('DELETE FROM role_permission WHERE role_id = :role_id')
                     ->execute(['role_id' => $roleId]);

            if (!empty($permissionIds)) {
                $stmt = $this->db->prepare(
                    'INSERT INTO role_permission (role_id, permission_id) VALUES (:role_id, :permission_id)'
                );

                foreach ($permissionIds as $pid) {
                    $stmt->execute(['role_id' => $roleId, 'permission_id' => (int) $pid]);
                }
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
