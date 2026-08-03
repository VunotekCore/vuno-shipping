<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class RouteModel
{
    public function __construct(private PDO $db) {}

    public function count(?string $status = null, ?int $driverId = null): int
    {
        $where = [];
        $params = [];

        if ($status !== null) {
            $where[] = 'rs.name = :status';
            $params['status'] = $status;
        }
        if ($driverId !== null) {
            $where[] = 'dr.assigned_to = :driver_id';
            $params['driver_id'] = $driverId;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM delivery_route dr LEFT JOIN route_status rs ON rs.id = dr.status_id {$whereClause}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function listPaginated(int $limit, int $offset, ?string $status = null, ?int $driverId = null): array
    {
        $where = [];
        $params = [];

        if ($status !== null) {
            $where[] = 'rs.name = :status';
            $params['status'] = $status;
        }
        if ($driverId !== null) {
            $where[] = 'dr.assigned_to = :driver_id';
            $params['driver_id'] = $driverId;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->db->prepare("
            SELECT dr.id, dr.name, dr.description, dr.assigned_to, u.name AS driver_name,
                   dr.vehicle_id, v.plate AS vehicle_plate, v.brand AS vehicle_brand,
                   dr.start_time, dr.end_time,
                   dr.status_id, rs.name AS status_name,
                   dr.color, dr.start_latitude, dr.start_longitude,
                   dr.end_latitude, dr.end_longitude,
                   dr.created_at, dr.updated_at,
                   (SELECT COUNT(*) FROM route_stop WHERE route_id = dr.id) AS stop_count,
                   (SELECT COUNT(*) FROM route_stop WHERE route_id = dr.id AND status_id = (SELECT id FROM route_stop_status WHERE name = 'pending')) AS pending_stops
            FROM delivery_route dr
            LEFT JOIN `user` u ON u.id = dr.assigned_to
            LEFT JOIN vehicle v ON v.id = dr.vehicle_id
            LEFT JOIN route_status rs ON rs.id = dr.status_id
            {$whereClause}
            ORDER BY dr.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT dr.*, u.name AS driver_name, u.email AS driver_email,
                   v.plate AS vehicle_plate, v.brand AS vehicle_brand, v.model AS vehicle_model,
                   rs.name AS status_name,
                   (SELECT COUNT(*) FROM route_stop WHERE route_id = dr.id) AS stop_count,
                   (SELECT COUNT(*) FROM route_stop WHERE route_id = dr.id AND status_id = (SELECT id FROM route_stop_status WHERE name = 'pending')) AS pending_stops,
                   (SELECT COUNT(*) FROM route_stop WHERE route_id = dr.id AND status_id = (SELECT id FROM route_stop_status WHERE name = 'visited')) AS visited_stops
            FROM delivery_route dr
            LEFT JOIN `user` u ON u.id = dr.assigned_to
            LEFT JOIN vehicle v ON v.id = dr.vehicle_id
            LEFT JOIN route_status rs ON rs.id = dr.status_id
            WHERE dr.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO delivery_route (name, description, assigned_to, vehicle_id,
             start_time, end_time, status_id, trajectory, color,
             start_latitude, start_longitude, end_latitude, end_longitude)
            VALUES (:name, :description, :assigned_to, :vehicle_id,
             :start_time, :end_time, :status_id, :trajectory, :color,
             :start_latitude, :start_longitude, :end_latitude, :end_longitude)
        ");
        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'status_id' => $data['status_id'] ?? null,
            'trajectory' => $data['trajectory'] ?? null,
            'color' => $data['color'] ?? '#22c55e',
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

        foreach (['name', 'description', 'assigned_to', 'vehicle_id',
                    'start_time', 'end_time', 'status_id', 'trajectory', 'optimized_geometry', 'color',
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

    public function getStatusId(string $name): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM route_status WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
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

    public function getCandidatePackages(int $routeId): array
    {
        $stmt = $this->db->prepare("
            SELECT p.id, p.tracking_number, p.recipient_name, p.recipient_address,
                   p.recipient_phone, p.recipient_city, p.recipient_department,
                   p.latitude, p.longitude,
                   p.zone_id, z.name AS zone_name, p.description, p.weight,
                   p.status_id, ps.name AS status_name,
                   p.assigned_to, u.name AS assigned_to_name
            FROM package p
            LEFT JOIN zone z ON z.id = p.zone_id
            LEFT JOIN package_status ps ON ps.id = p.status_id
            LEFT JOIN `user` u ON u.id = p.assigned_to
            WHERE p.zone_id IN (SELECT zone_id FROM route_zone WHERE route_id = :route_id)
              AND (p.status_id = (SELECT id FROM package_status WHERE name = 'pending')
                   OR p.status_id = (SELECT id FROM package_status WHERE name = 'received'))
            ORDER BY p.created_at DESC
        ");
        $stmt->execute(['route_id' => $routeId]);
        return $stmt->fetchAll();
    }

    public function getRoutesByDriver(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT dr.*, rs.name AS status_name
            FROM delivery_route dr
            LEFT JOIN route_status rs ON rs.id = dr.status_id
            WHERE dr.assigned_to = :user_id
            ORDER BY dr.created_at DESC
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function getActiveRouteByDriver(int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT dr.id, dr.name, dr.vehicle_id,
                   rs.name AS status_name
            FROM delivery_route dr
            LEFT JOIN route_status rs ON rs.id = dr.status_id
            WHERE dr.assigned_to = :user_id
              AND dr.status_id = (SELECT id FROM route_status WHERE name = 'active')
            ORDER BY dr.created_at DESC
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function hasOpenAssignment(string $field, int $value, ?int $excludeRouteId = null): ?array
    {
        if (!in_array($field, ['assigned_to', 'vehicle_id'], true)) {
            return null;
        }

        $sql = "SELECT dr.id, dr.name
                FROM delivery_route dr
                JOIN route_status rs ON rs.id = dr.status_id
                WHERE dr.{$field} = :value
                  AND rs.name IN ('draft', 'active')
                  AND (:exclude IS NULL OR dr.id != :exclude_id)
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'value'      => $value,
            'exclude'    => $excludeRouteId,
            'exclude_id' => $excludeRouteId,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
