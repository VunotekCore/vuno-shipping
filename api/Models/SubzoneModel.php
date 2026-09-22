<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class SubzoneModel
{
    public function __construct(private PDO $db) {}

    public function listByZone(int $zoneId): array
    {
        $stmt = $this->db->prepare('SELECT id, zone_id, name FROM subzone WHERE zone_id = :zone_id ORDER BY name ASC');
        $stmt->execute(['zone_id' => $zoneId]);
        return $stmt->fetchAll();
    }
}