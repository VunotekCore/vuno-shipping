<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class DriverLocationModel
{
    public function __construct(private PDO $db) {}

    public function upsert(int $userId, array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO driver_location (user_id, latitude, longitude, accuracy, speed,
             heading, battery_level, is_active, last_update)
            VALUES (:user_id, :latitude, :longitude, :accuracy, :speed,
             :heading, :battery_level, :is_active, NOW())
            ON DUPLICATE KEY UPDATE
             latitude = VALUES(latitude),
             longitude = VALUES(longitude),
             accuracy = VALUES(accuracy),
             speed = VALUES(speed),
             heading = VALUES(heading),
             battery_level = VALUES(battery_level),
             is_active = VALUES(is_active),
             last_update = NOW()
        ");
        $stmt->execute([
            'user_id' => $userId,
            'latitude' => (float) ($data['latitude'] ?? 0),
            'longitude' => (float) ($data['longitude'] ?? 0),
            'accuracy' => isset($data['accuracy']) ? (float) $data['accuracy'] : null,
            'speed' => isset($data['speed']) ? (float) $data['speed'] : null,
            'heading' => isset($data['heading']) ? (float) $data['heading'] : null,
            'battery_level' => isset($data['battery_level']) ? (float) $data['battery_level'] : null,
            'is_active' => $data['is_active'] ?? 1,
        ]);
    }

    public function getByUser(int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT dl.*, u.name AS driver_name, u.email AS driver_email
            FROM driver_location dl
            JOIN user u ON u.id = dl.user_id
            WHERE dl.user_id = :user_id LIMIT 1
        ");
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getAllActive(): array
    {
        $stmt = $this->db->query("
            SELECT dl.*, u.name AS driver_name, u.email AS driver_email
            FROM driver_location dl
            JOIN user u ON u.id = dl.user_id
            WHERE dl.is_active = 1
              AND dl.last_update >= NOW() - INTERVAL 5 MINUTE
        ");
        return $stmt->fetchAll();
    }

    public function getHistory(int $userId, string $from, string $to): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM location_history
            WHERE user_id = :user_id AND recorded_at BETWEEN :from AND :to
            ORDER BY recorded_at ASC
        ");
        $stmt->execute(['user_id' => $userId, 'from' => $from, 'to' => $to]);
        return $stmt->fetchAll();
    }

    public function logHistory(int $userId, ?int $vehicleId, ?int $routeId, float $lat, float $lng, ?float $accuracy, ?float $speed, ?float $heading, ?float $battery): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO location_history (user_id, vehicle_id, route_id, latitude, longitude,
             accuracy, speed, heading, battery_level, recorded_at)
            VALUES (:user_id, :vehicle_id, :route_id, :latitude, :longitude,
             :accuracy, :speed, :heading, :battery_level, NOW())
        ");
        $stmt->execute([
            'user_id' => $userId,
            'vehicle_id' => $vehicleId,
            'route_id' => $routeId,
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy' => $accuracy,
            'speed' => $speed,
            'heading' => $heading,
            'battery_level' => $battery,
        ]);
    }
}
