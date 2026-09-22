<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class RouteModel
{
    public function __construct(private PDO $db) {}

    public function count(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) FROM delivery_route');
        return (int) $stmt->fetchColumn();
    }

    public function listPaginated(int $limit, int $offset): array
    {
        $stmt = $this->db->prepare("
            SELECT dr.id, dr.name, dr.description, dr.color,
                   dr.default_driver_id, dr.default_vehicle_id,
                   dr.trajectory, dr.start_latitude, dr.start_longitude,
                   dr.end_latitude, dr.end_longitude, dr.created_at, dr.updated_at,
                   (SELECT u.name FROM `user` u WHERE u.id = dr.default_driver_id) AS default_driver_name,
                   (SELECT v.plate FROM vehicle v WHERE v.id = dr.default_vehicle_id) AS default_vehicle_plate,
                   (SELECT COUNT(*) FROM route_zone WHERE route_id = dr.id) AS zone_count,
                   (SELECT COUNT(*) FROM route_run WHERE route_id = dr.id) AS runs_count,
                   (SELECT rr.id FROM route_run rr
                      JOIN route_status rrs ON rrs.id = rr.status_id
                     WHERE rr.route_id = dr.id AND rr.run_date = CURDATE()
                       AND rrs.name IN ('draft', 'active')
                     LIMIT 1) AS open_run_id,
                   (SELECT rrs2.name FROM route_run rr2
                      JOIN route_status rrs2 ON rrs2.id = rr2.status_id
                     WHERE rr2.route_id = dr.id AND rr2.run_date = CURDATE()
                       AND rrs2.name IN ('draft', 'active')
                     LIMIT 1) AS open_run_status
            FROM delivery_route dr
            ORDER BY dr.created_at DESC
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
            SELECT dr.*,
                   (SELECT COUNT(*) FROM route_zone WHERE route_id = dr.id) AS zone_count
            FROM delivery_route dr
            WHERE dr.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO delivery_route (name, description, trajectory, color,
             default_driver_id, default_vehicle_id,
             start_latitude, start_longitude, end_latitude, end_longitude)
            VALUES (:name, :description, :trajectory, :color,
             :default_driver_id, :default_vehicle_id,
             :start_latitude, :start_longitude, :end_latitude, :end_longitude)
        ");
        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'trajectory' => $data['trajectory'] ?? null,
            'color' => $data['color'] ?? '#22c55e',
            'default_driver_id' => $data['default_driver_id'] ?? null,
            'default_vehicle_id' => $data['default_vehicle_id'] ?? null,
            'start_latitude' => $data['start_latitude'] ?? null,
            'start_longitude' => $data['start_longitude'] ?? null,
            'end_latitude' => $data['end_latitude'] ?? null,
            'end_longitude' => $data['end_longitude'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['name', 'description', 'trajectory', 'color',
                    'default_driver_id', 'default_vehicle_id',
                    'start_latitude', 'start_longitude', 'end_latitude', 'end_longitude'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) return false;

        $sql = 'UPDATE delivery_route SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM delivery_route WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function getZones(int $routeId): array
    {
        $stmt = $this->db->prepare("
            SELECT z.id, z.name, z.lat, z.lng
            FROM route_zone rz
            JOIN zone z ON z.id = rz.zone_id
            WHERE rz.route_id = :route_id
            ORDER BY rz.position ASC, rz.id ASC
        ");
        $stmt->execute(['route_id' => $routeId]);
        return $stmt->fetchAll();
    }

    public function getZonesForRoutes(array $routeIds): array
    {
        if (empty($routeIds)) return [];

        $placeholders = implode(',', array_fill(0, count($routeIds), '?'));
        $stmt = $this->db->prepare("
            SELECT rz.route_id, z.id, z.name, z.lat, z.lng
            FROM route_zone rz
            JOIN zone z ON z.id = rz.zone_id
            WHERE rz.route_id IN ({$placeholders})
            ORDER BY rz.route_id ASC, rz.position ASC, rz.id ASC
        ");
        $stmt->execute(array_values($routeIds));
        return $stmt->fetchAll();
    }

    public function setZones(int $routeId, array $zoneIds): void
    {
        $this->db->prepare('DELETE FROM route_zone WHERE route_id = :route_id')
            ->execute(['route_id' => $routeId]);

        if (!empty($zoneIds)) {
            $stmt = $this->db->prepare('INSERT INTO route_zone (route_id, zone_id, position) VALUES (:route_id, :zone_id, :position)');
            foreach ($zoneIds as $position => $zoneId) {
                $stmt->execute([
                    'route_id' => $routeId,
                    'zone_id' => (int) $zoneId,
                    'position' => (int) $position,
                ]);
            }
        }
    }
}
