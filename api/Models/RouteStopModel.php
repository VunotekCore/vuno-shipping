<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class RouteStopModel
{
    public function __construct(private PDO $db) {}

    public function listByRoute(int $routeId): array
    {
        $stmt = $this->db->prepare("
            SELECT rs.*, rss.name AS status_name,
                   p.tracking_number, p.recipient_phone, p.description AS package_description
            FROM route_stop rs
            LEFT JOIN route_stop_status rss ON rss.id = rs.status_id
            LEFT JOIN package p ON p.id = rs.package_id
            WHERE rs.route_id = :route_id
            ORDER BY rs.stop_order ASC
        ");
        $stmt->execute(['route_id' => $routeId]);
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
            INSERT INTO route_stop (route_id, package_id, stop_order, client_name, address,
             latitude, longitude, estimated_arrival, notes, status_id)
            VALUES (:route_id, :package_id, :stop_order, :client_name, :address,
             :latitude, :longitude, :estimated_arrival, :notes, :status_id)
        ");
        $stmt->execute([
            'route_id' => $data['route_id'],
            'package_id' => $data['package_id'] ?? null,
            'stop_order' => $data['stop_order'],
            'client_name' => $data['client_name'] ?? null,
            'address' => $data['address'],
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'estimated_arrival' => $data['estimated_arrival'] ?? null,
            'notes' => $data['notes'] ?? null,
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

    public function getRouteDriverId(int $stopId): ?int
    {
        $stmt = $this->db->prepare("
            SELECT dr.assigned_to
            FROM route_stop rs
            JOIN delivery_route dr ON dr.id = rs.route_id
            WHERE rs.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $stopId]);
        $val = $stmt->fetchColumn();
        return $val !== false && $val !== null ? (int) $val : null;
    }

    public function generateFromPackages(int $routeId, array $packageIds): array
    {
        $pendingStatusId = $this->getStatusId('pending');
        if ($pendingStatusId === null) throw new \RuntimeException('Stop status pending not found');

        $this->db->prepare('DELETE FROM route_stop WHERE route_id = :route_id')
            ->execute(['route_id' => $routeId]);

        $placeholders = implode(',', array_fill(0, count($packageIds), '?'));
        $stmt = $this->db->prepare("
            SELECT p.id, p.recipient_name, p.recipient_address,
                   z.lat, z.lng, p.zone_id
            FROM package p
            LEFT JOIN zone z ON z.id = p.zone_id
            WHERE p.id IN ({$placeholders})
        ");
        $stmt->execute($packageIds);
        $packages = $stmt->fetchAll();

        $created = [];
        $order = 1;

        $insertStmt = $this->db->prepare("
            INSERT INTO route_stop (route_id, package_id, stop_order, client_name, address,
             latitude, longitude, status_id)
            VALUES (:route_id, :package_id, :stop_order, :client_name, :address,
             :latitude, :longitude, :status_id)
        ");

        foreach ($packages as $pkg) {
            $insertStmt->execute([
                'route_id' => $routeId,
                'package_id' => (int) $pkg['id'],
                'stop_order' => $order,
                'client_name' => $pkg['recipient_name'],
                'address' => $pkg['recipient_address'],
                'latitude' => $pkg['lat'],
                'longitude' => $pkg['lng'],
                'status_id' => $pendingStatusId,
            ]);
            $created[] = [
                'id' => (int) $this->db->lastInsertId(),
                'stop_order' => $order,
                'package_id' => (int) $pkg['id'],
                'client_name' => $pkg['recipient_name'],
                'address' => $pkg['recipient_address'],
            ];
            $order++;
        }

        return $created;
    }
}
