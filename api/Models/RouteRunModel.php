<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class RouteRunModel
{
    public function __construct(private PDO $db) {}

    public function count(?string $status = null, ?int $driverId = null, ?int $routeId = null, ?string $date = null): int
    {
        $where = [];
        $params = [];

        if ($status !== null) {
            $where[] = 'rs.name = :status';
            $params['status'] = $status;
        }
        if ($driverId !== null) {
            $where[] = 'rr.assigned_to = :driver_id';
            $params['driver_id'] = $driverId;
        }
        if ($routeId !== null) {
            $where[] = 'rr.route_id = :route_id';
            $params['route_id'] = $routeId;
        }
        if ($date !== null) {
            $where[] = 'rr.run_date = :run_date';
            $params['run_date'] = $date;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM route_run rr LEFT JOIN route_status rs ON rs.id = rr.status_id {$whereClause}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function listPaginated(int $limit, int $offset, ?string $status = null, ?int $driverId = null, ?int $routeId = null, ?string $date = null): array
    {
        $where = [];
        $params = [];

        if ($status !== null) {
            $where[] = 'rs.name = :status';
            $params['status'] = $status;
        }
        if ($driverId !== null) {
            $where[] = 'rr.assigned_to = :driver_id';
            $params['driver_id'] = $driverId;
        }
        if ($routeId !== null) {
            $where[] = 'rr.route_id = :route_id';
            $params['route_id'] = $routeId;
        }
        if ($date !== null) {
            $where[] = 'rr.run_date = :run_date';
            $params['run_date'] = $date;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->db->prepare("
            SELECT rr.id, rr.route_id, dr.name AS route_name, dr.color AS route_color,
                   rr.run_date, rr.assigned_to, u.name AS driver_name,
                   rr.vehicle_id, v.plate AS vehicle_plate, v.brand AS vehicle_brand,
                   rr.start_time, rr.end_time,
                   rr.status_id, rs.name AS status_name,
                   rr.optimized_geometry, rr.notes,
                   rr.created_at, rr.updated_at,
                   (SELECT COUNT(*) FROM route_stop WHERE route_run_id = rr.id) AS stop_count,
                   (SELECT COUNT(*) FROM route_stop WHERE route_run_id = rr.id AND status_id = (SELECT id FROM route_stop_status WHERE name = 'pending')) AS pending_stops,
                   (SELECT COUNT(*) FROM route_stop WHERE route_run_id = rr.id AND status_id = (SELECT id FROM route_stop_status WHERE name = 'delivered')) AS delivered_stops
            FROM route_run rr
            JOIN delivery_route dr ON dr.id = rr.route_id
            LEFT JOIN `user` u ON u.id = rr.assigned_to
            LEFT JOIN vehicle v ON v.id = rr.vehicle_id
            LEFT JOIN route_status rs ON rs.id = rr.status_id
            {$whereClause}
            ORDER BY rr.run_date DESC, rr.created_at DESC
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
            SELECT rr.*, dr.name AS route_name, dr.description AS route_description, dr.color AS route_color,
                   dr.trajectory, dr.start_latitude, dr.start_longitude,
                   dr.end_latitude, dr.end_longitude,
                   u.name AS driver_name, u.email AS driver_email,
                   v.plate AS vehicle_plate, v.brand AS vehicle_brand, v.model AS vehicle_model,
                   rs.name AS status_name,
                   (SELECT COUNT(*) FROM route_stop WHERE route_run_id = rr.id) AS stop_count,
                   (SELECT COUNT(*) FROM route_stop WHERE route_run_id = rr.id AND status_id = (SELECT id FROM route_stop_status WHERE name = 'pending')) AS pending_stops,
                   (SELECT COUNT(*) FROM route_stop WHERE route_run_id = rr.id AND status_id = (SELECT id FROM route_stop_status WHERE name = 'delivered')) AS delivered_stops
            FROM route_run rr
            JOIN delivery_route dr ON dr.id = rr.route_id
            LEFT JOIN `user` u ON u.id = rr.assigned_to
            LEFT JOIN vehicle v ON v.id = rr.vehicle_id
            LEFT JOIN route_status rs ON rs.id = rr.status_id
            WHERE rr.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO route_run (route_id, run_date, assigned_to, vehicle_id,
             start_time, end_time, status_id, optimized_geometry, notes)
            VALUES (:route_id, :run_date, :assigned_to, :vehicle_id,
             :start_time, :end_time, :status_id, :optimized_geometry, :notes)
        ");
        $stmt->execute([
            'route_id' => $data['route_id'],
            'run_date' => $data['run_date'],
            'assigned_to' => $data['assigned_to'] ?? null,
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'status_id' => $data['status_id'] ?? null,
            'optimized_geometry' => $data['optimized_geometry'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['route_id', 'run_date', 'assigned_to', 'vehicle_id',
                    'start_time', 'end_time', 'status_id', 'optimized_geometry', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) return false;

        $sql = 'UPDATE route_run SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM route_run WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function getStatusId(string $name): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM route_status WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    public function getCandidatePackages(int $routeId, int $excludeRunId): array
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
              AND p.status_id = (SELECT id FROM package_status WHERE name = 'in_nicaragua')
              AND NOT EXISTS (
                  SELECT 1 FROM route_stop rs
                  WHERE rs.route_run_id = :exclude_run_id AND rs.package_id = p.id
              )
              AND NOT EXISTS (
                  SELECT 1 FROM route_stop rs2
                  JOIN route_run rr2 ON rr2.id = rs2.route_run_id
                  LEFT JOIN route_status rrs2 ON rrs2.id = rr2.status_id
                  WHERE rs2.package_id = p.id
                    AND rr2.id != :exclude_run_id2
                    AND rrs2.name IN ('draft', 'active')
              )
            ORDER BY
                (SELECT rz.position FROM route_zone rz WHERE rz.route_id = :route_id2 AND rz.zone_id = p.zone_id LIMIT 1) ASC,
                p.created_at DESC
        ");
        $stmt->execute([
            'route_id' => $routeId,
            'route_id2' => $routeId,
            'exclude_run_id' => $excludeRunId,
            'exclude_run_id2' => $excludeRunId,
        ]);
        return $stmt->fetchAll();
    }

    public function getRoutesByDriver(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT rr.*, dr.name AS route_name, dr.color AS route_color, rs.name AS status_name
            FROM route_run rr
            JOIN delivery_route dr ON dr.id = rr.route_id
            LEFT JOIN route_status rs ON rs.id = rr.status_id
            WHERE rr.assigned_to = :user_id
            ORDER BY rr.run_date DESC, rr.created_at DESC
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function getActiveRunByDriver(int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT rr.id, rr.vehicle_id, rs.name AS status_name
            FROM route_run rr
            LEFT JOIN route_status rs ON rs.id = rr.status_id
            WHERE rr.assigned_to = :user_id
              AND rr.status_id = (SELECT id FROM route_status WHERE name = 'active')
            ORDER BY rr.run_date DESC, rr.created_at DESC
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function hasOpenAssignment(string $field, int $value, ?int $excludeRunId = null): ?array
    {
        if (!in_array($field, ['assigned_to', 'vehicle_id'], true)) {
            return null;
        }

        $sql = "SELECT rr.id, dr.name
                FROM route_run rr
                JOIN delivery_route dr ON dr.id = rr.route_id
                JOIN route_status rs ON rs.id = rr.status_id
                WHERE rr.{$field} = :value
                  AND rs.name IN ('draft', 'active')
                  AND (:exclude IS NULL OR rr.id != :exclude_id)
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'value'      => $value,
            'exclude'    => $excludeRunId,
            'exclude_id' => $excludeRunId,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function hasOpenRunForDate(int $routeId, string $date, ?int $excludeRunId = null): ?array
    {
        $sql = "SELECT rr.id, dr.name
                FROM route_run rr
                JOIN delivery_route dr ON dr.id = rr.route_id
                JOIN route_status rs ON rs.id = rr.status_id
                WHERE rr.route_id = :route_id
                  AND rr.run_date = :run_date
                  AND rs.name IN ('draft', 'active')
                  AND (:exclude IS NULL OR rr.id != :exclude_id)
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'route_id'   => $routeId,
            'run_date'   => $date,
            'exclude'    => $excludeRunId,
            'exclude_id' => $excludeRunId,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getLastRunForRoute(int $routeId, ?int $excludeRunId = null): ?array
    {
        $sql = "SELECT rr.id, rr.run_date
                FROM route_run rr
                WHERE rr.route_id = :route_id
                  AND (:exclude IS NULL OR rr.id != :exclude_id)
                ORDER BY rr.run_date DESC, rr.created_at DESC
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'route_id'   => $routeId,
            'exclude'    => $excludeRunId,
            'exclude_id' => $excludeRunId,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
