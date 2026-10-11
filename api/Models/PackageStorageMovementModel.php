<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class PackageStorageMovementModel
{
    public function __construct(private PDO $db) {}

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO package_storage_movement
                (package_id, warehouse_id, location_id, category_id, action, notes, created_by)
            VALUES (:package_id, :warehouse_id, :location_id, :category_id, :action, :notes, :created_by)
        ");
        $stmt->execute([
            'package_id' => $data['package_id'],
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'location_id' => $data['location_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'action' => $data['action'],
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function listByPackage(int $packageId): array
    {
        $stmt = $this->db->prepare("
            SELECT psm.*,
                   w.name AS warehouse_name, w.code AS warehouse_code,
                   sl.code AS location_code,
                   sc.name AS category_name, sc.color AS category_color,
                   u.name AS created_by_name
            FROM package_storage_movement psm
            LEFT JOIN warehouse w ON w.id = psm.warehouse_id
            LEFT JOIN storage_location sl ON sl.id = psm.location_id
            LEFT JOIN storage_category sc ON sc.id = psm.category_id
            LEFT JOIN `user` u ON u.id = psm.created_by
            WHERE psm.package_id = :package_id
            ORDER BY psm.id DESC
        ");
        $stmt->execute(['package_id' => $packageId]);
        return $stmt->fetchAll();
    }
}
