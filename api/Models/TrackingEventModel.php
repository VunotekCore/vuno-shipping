<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class TrackingEventModel
{
    public function __construct(private PDO $db) {}

    public function listByPackage(int $packageId): array
    {
        $stmt = $this->db->prepare("
            SELECT te.*, u.name AS created_by_name
            FROM tracking_event te
            LEFT JOIN `user` u ON u.id = te.created_by
            WHERE te.package_id = :package_id
            ORDER BY te.recorded_at DESC
        ");
        $stmt->execute(['package_id' => $packageId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO tracking_event (package_id, status, description, latitude, longitude, location_name, photo_url, recorded_at, created_by)
             VALUES (:package_id, :status, :description, :latitude, :longitude, :location_name, :photo_url, :recorded_at, :created_by)'
        );
        $stmt->execute([
            'package_id' => $data['package_id'],
            'status' => $data['status'],
            'description' => $data['description'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'location_name' => $data['location_name'] ?? null,
            'photo_url' => $data['photo_url'] ?? null,
            'recorded_at' => $data['recorded_at'] ?? date('Y-m-d H:i:s'),
            'created_by' => $data['created_by'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }
}
