<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class PermissionModel
{
    public function __construct(private PDO $db) {}

    public function list(): array
    {
        $stmt = $this->db->query("
            SELECT id, module, action, label, created_at
            FROM permission
            ORDER BY module, action
        ");
        return $stmt->fetchAll();
    }

    public function count(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM permission")->fetchColumn();
    }

    public function listPaginated(int $limit, int $offset): array
    {
        $stmt = $this->db->prepare("
            SELECT id, module, action, label, created_at
            FROM permission
            ORDER BY module, action
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, module, action, label, created_at FROM permission WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByModuleAction(string $module, string $action): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, module, action, label FROM permission WHERE module = :module AND action = :action LIMIT 1'
        );
        $stmt->execute(['module' => $module, 'action' => $action]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $module, string $action, string $label = ''): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO permission (module, action, label) VALUES (:module, :action, :label)'
        );
        $stmt->execute([
            'module' => $module,
            'action' => $action,
            'label'  => $label,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $module, string $action, string $label): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE permission SET module = :module, action = :action, label = :label WHERE id = :id'
        );
        return $stmt->execute([
            'id'     => $id,
            'module' => $module,
            'action' => $action,
            'label'  => $label,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM permission WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function countRolesWithPermission(int $permissionId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM role_permission WHERE permission_id = :id'
        );
        $stmt->execute(['id' => $permissionId]);
        return (int) $stmt->fetchColumn();
    }

    public function countUsersWithPermission(int $permissionId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM user_permission WHERE permission_id = :id'
        );
        $stmt->execute(['id' => $permissionId]);
        return (int) $stmt->fetchColumn();
    }
}
