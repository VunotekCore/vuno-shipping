<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class VehicleModel
{
    public const ALLOWED_STATUSES = [
        'disponible',
        'en_ruta',
        'en_espera',
        'en_mantenimiento',
        'en_reparacion',
        'fuera_de_servicio',
        'accidentado',
        'dado_de_baja',
    ];

    public const ALLOWED_READING_TYPES = [
        'manual',
        'inicio_ruta',
        'fin_ruta',
        'entrega_llaves',
        'devolucion_llaves',
        'inspeccion',
        'mantenimiento',
        'reparacion',
        'otro',
    ];

    public function __construct(private PDO $db) {}

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM vehicle')->fetchColumn();
    }

    public function listPaginated(int $limit, int $offset): array
    {
        $stmt = $this->db->prepare("
            SELECT v.*,
                   u.name AS driver_name
            FROM vehicle v
            LEFT JOIN `user` u ON u.id = v.current_driver_id
            ORDER BY v.plate ASC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listAll(): array
    {
        $stmt = $this->db->query("
            SELECT v.id, v.plate, v.brand, v.model, v.status, v.current_km
            FROM vehicle v
            ORDER BY v.plate ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT v.*,
                   u.name AS driver_name,
                   ku.name AS km_last_updated_by_name
            FROM vehicle v
            LEFT JOIN `user` u ON u.id = v.current_driver_id
            LEFT JOIN `user` ku ON ku.id = v.km_last_updated_by
            WHERE v.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO vehicle (plate, brand, model, year, color, fuel_type, capacity_kg, capacity_m3, current_driver_id, status, current_km, notes)
             VALUES (:plate, :brand, :model, :year, :color, :fuel_type, :capacity_kg, :capacity_m3, :current_driver_id, :status, :current_km, :notes)'
        );
        $stmt->execute([
            'plate' => $data['plate'],
            'brand' => $data['brand'] ?? null,
            'model' => $data['model'] ?? null,
            'year' => $data['year'] ?? null,
            'color' => $data['color'] ?? null,
            'fuel_type' => $data['fuel_type'] ?? null,
            'capacity_kg' => $data['capacity_kg'] ?? null,
            'capacity_m3' => $data['capacity_m3'] ?? null,
            'current_driver_id' => $data['current_driver_id'] ?? null,
            'status' => $data['status'] ?? 'disponible',
            'current_km' => $data['current_km'] ?? 0.00,
            'notes' => $data['notes'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['plate', 'brand', 'model', 'year', 'color', 'fuel_type', 'capacity_kg', 'capacity_m3', 'current_driver_id', 'status', 'current_km', 'notes'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE vehicle SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM vehicle WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function validateDriverExists(?int $driverId): bool
    {
        if ($driverId === null) {
            return true;
        }
        $stmt = $this->db->prepare('SELECT 1 FROM `user` WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $driverId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    public function validateStatus(string $status): bool
    {
        return in_array($status, self::ALLOWED_STATUSES, true);
    }

    public function registerOdometerReading(
        int $vehicleId,
        float $kmReading,
        string $readingType,
        int $userId,
        ?int $routeRunId,
        ?string $notes,
        bool $allowCorrection,
        ?int $routeRunAssignedTo
    ): array {
        $this->db->beginTransaction();
        try {
            $stmtV = $this->db->prepare('SELECT id, current_km, plate FROM vehicle WHERE id = :id FOR UPDATE');
            $stmtV->execute(['id' => $vehicleId]);
            $veh = $stmtV->fetch(PDO::FETCH_ASSOC);
            if (!$veh) {
                $this->db->rollBack();
                throw new \RuntimeException('Vehículo no encontrado', 404);
            }

            $previousKm = (float) $veh['current_km'];
            $newKm = (float) round($kmReading, 1);

            $isRouteType = in_array($readingType, ['inicio_ruta', 'fin_ruta', 'entrega_llaves', 'devolucion_llaves'], true);
            if ($isRouteType && $routeRunAssignedTo !== null && $routeRunAssignedTo === $userId) {
                $this->db->rollBack();
                throw new \RuntimeException('El registro de km de salida/llegada debe hacerlo personal distinto al conductor asignado al despacho', 409);
            }

            $delta = $newKm - $previousKm;
            if ($delta < 0) {
                if (!$allowCorrection) {
                    $this->db->rollBack();
                    throw new \RuntimeException('No se permite bajar el kilometraje. Requiere permiso de corrección', 422);
                }
                if (trim((string) $notes) === '') {
                    $this->db->rollBack();
                    throw new \RuntimeException('La corrección de kilometraje requiere justificación (notes)', 422);
                }
            }

            $stmtLog = $this->db->prepare(
                'INSERT INTO vehicle_odometer_log (vehicle_id, km_reading, previous_km, delta_km, reading_type, route_run_id, user_id, notes)
                 VALUES (:vehicle_id, :km_reading, :previous_km, :delta_km, :reading_type, :route_run_id, :user_id, :notes)'
            );
            $stmtLog->execute([
                'vehicle_id' => $vehicleId,
                'km_reading' => $newKm,
                'previous_km' => $previousKm,
                'delta_km' => round($delta, 1),
                'reading_type' => $readingType,
                'route_run_id' => $routeRunId,
                'user_id' => $userId,
                'notes' => $notes !== null && $notes !== '' ? trim($notes) : null,
            ]);
            $logId = (int) $this->db->lastInsertId();

            $stmtUp = $this->db->prepare(
                'UPDATE vehicle SET current_km = :km, km_last_updated_at = NOW(), km_last_updated_by = :user WHERE id = :id'
            );
            $stmtUp->execute(['km' => $newKm, 'user' => $userId, 'id' => $vehicleId]);

            $this->db->commit();
            return ['log_id' => $logId, 'previous_km' => $previousKm, 'delta_km' => round($delta, 1), 'current_km' => $newKm];
        } catch (\RuntimeException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new \RuntimeException('Error al registrar lectura de odómetro', 500);
        }
    }

    public function getOdometerLogs(int $vehicleId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
            SELECT l.*, u.name AS user_name, r.run_date
            FROM vehicle_odometer_log l
            LEFT JOIN `user` u ON u.id = l.user_id
            LEFT JOIN route_run r ON r.id = l.route_run_id
            WHERE l.vehicle_id = :v
            ORDER BY l.id DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('v', $vehicleId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countOdometerLogs(int $vehicleId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM vehicle_odometer_log WHERE vehicle_id = :v');
        $stmt->execute(['v' => $vehicleId]);
        return (int) $stmt->fetchColumn();
    }

    public function getRouteRunAssignedTo(?int $routeRunId): ?int
    {
        if ($routeRunId === null) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT assigned_to FROM route_run WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $routeRunId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r && $r['assigned_to'] !== null ? (int) $r['assigned_to'] : null;
    }
}
