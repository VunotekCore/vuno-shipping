<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class UserModel
{
    public function __construct(private PDO $db) {}

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name AS role_name, r.slug AS role_slug
            FROM `user` u
            JOIN role r ON r.id = u.role_id
            WHERE u.email = :email LIMIT 1
        ");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.id, u.email, u.name, u.role_id, r.name AS role_name, r.slug AS role_slug, u.created_at
            FROM `user` u
            JOIN role r ON r.id = u.role_id
            WHERE u.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findWithPermissions(int $id): ?array
    {
        $user = $this->findById($id);
        if ($user === null) {
            return null;
        }

        $user['permissions'] = $this->getEffectivePermissions($user['role_id'], $id);
        return $user;
    }

    public function getEffectivePermissions(int $roleId, int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT p.module, p.action FROM role_permission rp
            JOIN permission p ON p.id = rp.permission_id
            WHERE rp.role_id = :role_id
        ");
        $stmt->execute(['role_id' => $roleId]);
        $rolePerms = $stmt->fetchAll();

        $stmt = $this->db->prepare("
            SELECT p.module, p.action, up.granted FROM user_permission up
            JOIN permission p ON p.id = up.permission_id
            WHERE up.user_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);
        $userPerms = $stmt->fetchAll();

        $permissions = [];
        foreach ($rolePerms as $p) {
            $permissions[$p['module']][] = $p['action'];
        }
        foreach ($userPerms as $p) {
            if ($p['granted']) {
                if (!isset($permissions[$p['module']])) {
                    $permissions[$p['module']] = [];
                }
                if (!in_array($p['action'], $permissions[$p['module']], true)) {
                    $permissions[$p['module']][] = $p['action'];
                }
            } else {
                if (isset($permissions[$p['module']])) {
                    $permissions[$p['module']] = array_values(
                        array_diff($permissions[$p['module']], [$p['action']])
                    );
                }
            }
        }

        return $permissions;
    }

    public function count(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM `user`")->fetchColumn();
    }

    public function countByRoleSlug(string $slug): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM `user`
            WHERE role_id = (SELECT id FROM role WHERE slug = :slug LIMIT 1)
        ");
        $stmt->execute(['slug' => $slug]);
        return (int) $stmt->fetchColumn();
    }

    public function listPaginated(int $limit, int $offset): array
    {
        $stmt = $this->db->prepare("
            SELECT u.id, u.email, u.name, u.role_id, r.name AS role_name, r.slug AS role_slug, u.created_at
            FROM `user` u
            JOIN role r ON r.id = u.role_id
            ORDER BY u.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(string $email, string $password, string $name, int $roleId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO `user` (email, password, name, role_id) VALUES (:email, :password, :name, :role_id)'
        );
        $stmt->execute([
            'email'    => $email,
            'password' => $password,
            'name'     => $name,
            'role_id'  => $roleId,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['email', 'name', 'role_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (!empty($data['password'])) {
            $fields[] = 'password = :password';
            $params['password'] = $data['password'];
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE `user` SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM `user` WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}
