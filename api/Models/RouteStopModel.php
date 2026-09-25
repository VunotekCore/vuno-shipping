<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class RouteStopModel
{
    public function __construct(private PDO $db) {}

    public function listByRun(int $runId): array
    {
        $stmt = $this->db->prepare("
            SELECT rs.*, rss.name AS status_name,
                   p.tracking_number, p.recipient_phone, p.description AS package_description,
                   p.zone_id, z.name AS zone_name,
                   CASE WHEN p.latitude IS NULL OR p.longitude IS NULL THEN 1 ELSE 0 END AS from_zone_center
            FROM route_stop rs
            LEFT JOIN route_stop_status rss ON rss.id = rs.status_id
            LEFT JOIN package p ON p.id = rs.package_id
            LEFT JOIN zone z ON z.id = p.zone_id
            WHERE rs.route_run_id = :route_run_id
            ORDER BY rs.stop_order ASC
        ");
        $stmt->execute(['route_run_id' => $runId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT rs.*, rss.name AS status_name,
                   p.tracking_number, p.recipient_name, p.recipient_phone,
                   p.description AS package_description
            FROM route_stop rs
            LEFT JOIN route_stop_status rss ON rss.id = rs.status_id
            LEFT JOIN package p ON p.id = rs.package_id
            WHERE rs.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO route_stop (route_run_id, package_id, stop_order, client_name, address,
             latitude, longitude, estimated_arrival, notes, carried_over, status_id)
            VALUES (:route_run_id, :package_id, :stop_order, :client_name, :address,
             :latitude, :longitude, :estimated_arrival, :notes, :carried_over, :status_id)
        ");
        $stmt->execute([
            'route_run_id' => $data['route_run_id'],
            'package_id' => $data['package_id'] ?? null,
            'stop_order' => $data['stop_order'],
            'client_name' => $data['client_name'] ?? null,
            'address' => $data['address'],
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'estimated_arrival' => $data['estimated_arrival'] ?? null,
            'notes' => $data['notes'] ?? null,
            'carried_over' => $data['carried_over'] ?? 0,
            'status_id' => $data['status_id'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['stop_order', 'client_name', 'address', 'latitude', 'longitude',
                    'estimated_arrival', 'actual_arrival', 'notes', 'status_id',
                    'signature_data', 'photo_url'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) return false;

        $sql = 'UPDATE route_stop SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM route_stop WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function getStatusId(string $name): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM route_stop_status WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    public function getRunDriverId(int $stopId): ?int
    {
        $stmt = $this->db->prepare("
            SELECT rr.assigned_to
            FROM route_stop rs
            JOIN route_run rr ON rr.id = rs.route_run_id
            WHERE rs.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $stopId]);
        $val = $stmt->fetchColumn();
        return $val !== false && $val !== null ? (int) $val : null;
    }

    public function getRunIdByStop(int $stopId): ?int
    {
        $stmt = $this->db->prepare('SELECT route_run_id FROM route_stop WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $stopId]);
        $val = $stmt->fetchColumn();
        return $val !== false && $val !== null ? (int) $val : null;
    }

    public function getRunStatusByStop(int $stopId): ?string
    {
        $stmt = $this->db->prepare("
            SELECT rs.name
            FROM route_stop stop
            JOIN route_run rr ON rr.id = stop.route_run_id
            LEFT JOIN route_status rs ON rs.id = rr.status_id
            WHERE stop.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $stopId]);
        $val = $stmt->fetchColumn();
        return $val !== false && $val !== null ? (string) $val : null;
    }

    public function getMaxStopOrder(int $runId): int
    {
        $stmt = $this->db->prepare('SELECT COALESCE(MAX(stop_order), 0) FROM route_stop WHERE route_run_id = :route_run_id');
        $stmt->execute(['route_run_id' => $runId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Agrega paradas para los paquetes indicados. Aditivo: ignora paquetes que ya
     * tienen una parada en el run (no borra las paradas existentes).
     *
     * @return array{created: array, stops: array}
     */
    public function generateFromPackages(int $runId, array $packageIds): array
    {
        $pendingStatusId = $this->getStatusId('pending');
        if ($pendingStatusId === null) throw new \RuntimeException('Stop status pending not found');

        if (empty($packageIds)) {
            return ['created' => [], 'stops' => $this->listByRun($runId)];
        }

        $placeholders = implode(',', array_fill(0, count($packageIds), '?'));
        $stmt = $this->db->prepare("
            SELECT p.id, p.recipient_name, p.recipient_address,
                   p.latitude, p.longitude, z.lat, z.lng, p.zone_id
            FROM package p
            LEFT JOIN zone z ON z.id = p.zone_id
            WHERE p.id IN ({$placeholders})
              AND NOT EXISTS (
                  SELECT 1 FROM route_stop rs
                  WHERE rs.route_run_id = ? AND rs.package_id = p.id
              )
              AND NOT EXISTS (
                  SELECT 1 FROM route_stop rs2
                  JOIN route_run rr2 ON rr2.id = rs2.route_run_id
                  LEFT JOIN route_status rrs2 ON rrs2.id = rr2.status_id
                  WHERE rs2.package_id = p.id
                    AND rr2.id != ?
                    AND rrs2.name IN ('draft', 'active')
              )
        ");
        $stmt->execute(array_merge(array_map('intval', $packageIds), [$runId, $runId]));
        $packages = $stmt->fetchAll();

        $created = [];
        $order = $this->getMaxStopOrder($runId) + 1;

        $insertStmt = $this->db->prepare("
            INSERT INTO route_stop (route_run_id, package_id, stop_order, client_name, address,
             latitude, longitude, status_id)
            VALUES (:route_run_id, :package_id, :stop_order, :client_name, :address,
             :latitude, :longitude, :status_id)
        ");

        foreach ($packages as $pkg) {
            $lat = $pkg['latitude'] !== null && $pkg['latitude'] !== '' ? $pkg['latitude'] : $pkg['lat'];
            $lng = $pkg['longitude'] !== null && $pkg['longitude'] !== '' ? $pkg['longitude'] : $pkg['lng'];

            $insertStmt->execute([
                'route_run_id' => $runId,
                'package_id' => (int) $pkg['id'],
                'stop_order' => $order,
                'client_name' => $pkg['recipient_name'],
                'address' => $pkg['recipient_address'],
                'latitude' => $lat,
                'longitude' => $lng,
                'status_id' => $pendingStatusId,
            ]);
            $created[] = [
                'id' => (int) $this->db->lastInsertId(),
                'stop_order' => $order,
                'package_id' => (int) $pkg['id'],
                'client_name' => $pkg['recipient_name'],
                'address' => $pkg['recipient_address'],
                'latitude' => $lat,
                'longitude' => $lng,
                'from_zone_center' => ($pkg['latitude'] === null || $pkg['latitude'] === '') ? 1 : 0,
                'carried_over' => 0,
            ];
            $order++;
        }

        return ['created' => $created, 'stops' => $this->listByRun($runId)];
    }

    /**
     * Arrastre automatico: copia al nuevo run las paradas NO entregadas (pending,
     * skipped o failed) del run anterior, conservando la observacion (notes).
     *
     * @return array{created: array, stops: array}
     */
    public function carryForward(int $newRunId, int $previousRunId): array
    {
        $pendingStatusId = $this->getStatusId('pending');
        $deliveredStatusId = $this->getStatusId('delivered');
        if ($pendingStatusId === null || $deliveredStatusId === null) {
            throw new \RuntimeException('Stop status pending/delivered not found');
        }

        $stmt = $this->db->prepare("
            SELECT rs.package_id, rs.client_name, rs.address, rs.notes AS observation,
                   p.latitude AS pkg_lat, p.longitude AS pkg_lng,
                   z.lat AS zone_lat, z.lng AS zone_lng
            FROM route_stop rs
            LEFT JOIN package p ON p.id = rs.package_id
            LEFT JOIN zone z ON z.id = p.zone_id
            WHERE rs.route_run_id = :prev_run_id
              AND rs.package_id IS NOT NULL
              AND (rs.status_id IS NULL OR rs.status_id != :delivered_id)
            ORDER BY rs.stop_order ASC
        ");
        $stmt->execute(['prev_run_id' => $previousRunId, 'delivered_id' => $deliveredStatusId]);
        $carryOver = $stmt->fetchAll();

        $created = [];
        $order = $this->getMaxStopOrder($newRunId) + 1;

        $insertStmt = $this->db->prepare("
            INSERT INTO route_stop (route_run_id, package_id, stop_order, client_name, address,
             latitude, longitude, notes, carried_over, status_id)
            VALUES (:route_run_id, :package_id, :stop_order, :client_name, :address,
             :latitude, :longitude, :notes, :carried_over, :status_id)
        ");

        foreach ($carryOver as $row) {
            $lat = ($row['pkg_lat'] !== null && $row['pkg_lat'] !== '') ? $row['pkg_lat'] : $row['zone_lat'];
            $lng = ($row['pkg_lng'] !== null && $row['pkg_lng'] !== '') ? $row['pkg_lng'] : $row['zone_lng'];

            $insertStmt->execute([
                'route_run_id' => $newRunId,
                'package_id' => (int) $row['package_id'],
                'stop_order' => $order,
                'client_name' => $row['client_name'],
                'address' => $row['address'],
                'latitude' => $lat,
                'longitude' => $lng,
                'notes' => $row['observation'] ?? null,
                'carried_over' => 1,
                'status_id' => $pendingStatusId,
            ]);
            $created[] = [
                'id' => (int) $this->db->lastInsertId(),
                'stop_order' => $order,
                'package_id' => (int) $row['package_id'],
                'client_name' => $row['client_name'],
                'address' => $row['address'],
                'latitude' => $lat,
                'longitude' => $lng,
                'from_zone_center' => ($row['pkg_lat'] === null || $row['pkg_lat'] === '') ? 1 : 0,
                'carried_over' => 1,
                'notes' => $row['observation'] ?? null,
            ];
            $order++;
        }

        return ['created' => $created, 'stops' => $this->listByRun($newRunId)];
    }
}
