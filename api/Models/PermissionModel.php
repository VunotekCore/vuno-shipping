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

    public function listModules(): array
    {
        $stmt = $this->db->query(
            "SELECT DISTINCT module FROM permission ORDER BY module"
        );
        return array_column($stmt->fetchAll(), 'module');
    }

    public function findByModule(string $module): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, module, action, label FROM permission WHERE module = :module'
        );
        $stmt->execute(['module' => $module]);
        return $stmt->fetchAll();
    }

    public function syncFromDatabase(): array
    {
        $excluded = [
            'permission', 'token_blacklist', 'refresh_tokens',
            'route_status', 'route_zone', 'route_run', 'route_stop_status',
            'route_stop', 'driver_location', 'location_history',
        ];
        $singleRecordModules = ['company'];

        $defaultActions = ['create', 'read', 'edit', 'delete'];
        $singleRecordActions = ['read', 'edit'];

        $stmt = $this->db->query("SHOW TABLES");
        $tables = array_column($stmt->fetchAll(\PDO::FETCH_NUM), 0);

        $created = [];
        $skipped = [];

        $this->db->beginTransaction();

        try {
            foreach ($tables as $table) {
                if (in_array($table, $excluded, true)) {
                    $skipped[] = ['module' => $table, 'reason' => 'excluded'];
                    continue;
                }

                $check = $this->db->prepare(
                    'SELECT COUNT(*) FROM permission WHERE module = :module'
                );
                $check->execute(['module' => $table]);
                $existingCount = (int) $check->fetchColumn();

                if ($existingCount > 0) {
                    $skipped[] = ['module' => $table, 'reason' => 'exists', 'count' => $existingCount];
                    continue;
                }

                $actions = in_array($table, $singleRecordModules, true)
                    ? $singleRecordActions
                    : $defaultActions;

                foreach ($actions as $action) {
                    $label = $this->generateLabel($table, $action);
                    $insert = $this->db->prepare(
                        'INSERT INTO permission (module, action, label) VALUES (:module, :action, :label)'
                    );
                    $insert->execute([
                        'module' => $table,
                        'action' => $action,
                        'label' => $label,
                    ]);
                    $permId = (int) $this->db->lastInsertId();

                    $assignAdmin = $this->db->prepare(
                        'INSERT IGNORE INTO role_permission (role_id, permission_id) VALUES (:role_id, :permission_id)'
                    );
                    $assignAdmin->execute([
                        'role_id' => 1,
                        'permission_id' => $permId,
                    ]);

                    $created[] = ['module' => $table, 'action' => $action, 'label' => $label];
                }
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    private function generateLabel(string $table, string $action): string
    {
        $moduleName = ucfirst($table);
        $actionLabels = [
            'create' => 'Crear',
            'read'   => 'Ver',
            'edit'   => 'Editar',
            'delete' => 'Eliminar',
        ];
        $actionLabel = $actionLabels[$action] ?? ucfirst($action);
        return "{$actionLabel} {$moduleName}";
    }
}
